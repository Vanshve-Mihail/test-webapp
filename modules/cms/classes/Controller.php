<?php namespace Cms\Classes;

use Cms;
use Url;
use App;
use View;
use File;
use Lang;
use Log;
use Flash;
use Cache;
use Config;
use Session;
use Request;
use Response;
use Exception;
use SystemException;
use BackendAuth;
use Twig\Environment as TwigEnvironment;
use Cms\Models\MaintenanceSetting;
use System\Models\RequestLog;
use System\Helpers\View as ViewHelper;
use System\Classes\CombineAssets;
use Winter\Storm\Exception\AjaxException;
use Winter\Storm\Exception\ValidationException;
use Winter\Storm\Parse\Bracket as TextParser;
use Illuminate\Http\RedirectResponse;

/**
 * The CMS controller class.
 * The controller finds and serves requested pages.
 *
 * @package winter\wn-cms-module
 * @author Alexey Bobkov, Samuel Georges
 */
class Controller
{
    use \System\Traits\AssetMaker;
    use \System\Traits\EventEmitter;
    use \System\Traits\ResponseMaker;
    use \System\Traits\SecurityController;

    /**
     * @var \Cms\Classes\Theme A reference to the CMS theme processed by the controller.
     */
    protected $theme;

    /**
     * @var \Cms\Classes\Router A reference to the Router object.
     */
    protected $router;

    /**
     * @var \Cms\Classes\Page A reference to the CMS page template being processed.
     */
    protected $page;

    /**
     * @var \Cms\Classes\CodeBase A reference to the CMS page code section object.
     */
    protected $pageObj;

    /**
     * @var \Cms\Classes\Layout A reference to the CMS layout template used by the page.
     */
    protected $layout;

    /**
     * @var \Cms\Classes\CodeBase A reference to the CMS layout code section object.
     */
    protected $layoutObj;

    /**
     * @var TwigEnvironment Keeps the Twig environment object.
     */
    protected $twig;

    /**
     * @var string Contains the rendered page contents string.
     */
    protected $pageContents;

    /**
     * @var array A list of variables to pass to the page.
     */
    public $vars = [];

    /**
     * @var self Cache of self
     */
    protected static $instance;

    /**
     * @var \Cms\Classes\ComponentBase Object of the active component, used internally.
     */
    protected $componentContext;

    /**
     * @var PartialStack Component partial stack, used internally.
     */
    protected $partialStack;

    /**
     * Creates the controller.
     * @param \Cms\Classes\Theme $theme Specifies the CMS theme.
     * If the theme is not specified, the current active theme used.
     *
     * @throws SystemException if the provided theme can't be found
     * @return void
     */
    public function __construct($theme = null)
    {
        $this->theme = $theme ?: Theme::getActiveTheme();
        if (!$this->theme) {
            throw new SystemException(Lang::get('cms::lang.theme.active.not_found'));
        }

        $this->assetPath = Config::get('cms.themesPath', '/themes') . '/' . $this->theme->getDirName();
        $this->router = new Router($this->theme);
        $this->partialStack = new PartialStack;
        $this->initTwigEnvironment();

        self::$instance = $this;
    }

    /**
     * Finds and serves the requested page.
     * If the page cannot be found, returns the page with the URL /404.
     * If the /404 page doesn't exist, returns the system 404 page.
     * If the parameter is null, the current URL used. If it is not
     * provided then '/' is used
     *
     * @param string|null $url Specifies the requested page URL.
     * @return BaseResponse Returns the response to the provided URL
     */
    public function run($url = '/')
    {
        if ($url === null) {
            $url = Request::path();
        }

        if (trim($url) === '') {
            $url = '/';
        }

        /*
         * Hidden page
         */
        $page = $this->router->findByUrl($url);
        if ($page && $page->is_hidden && !BackendAuth::getUser()) {
            $page = null;
        }

        /*
         * Maintenance mode
         */
        if (
            MaintenanceSetting::isConfigured() &&
            MaintenanceSetting::get('is_enabled', false) &&
            !MaintenanceSetting::isAllowedIp(Request::ip()) &&
            !BackendAuth::getUser()
        ) {
            if (!Request::ajax()) {
                $this->setStatusCode(503);
            }

            $page = Page::loadCached($this->theme, MaintenanceSetting::get('cms_page'));
        }

        /**
         * @event cms.page.beforeDisplay
         * Provides an opportunity to swap the page that gets displayed immediately after loading the page assigned to the URL.
         *
         * Example usage:
         *
         *     Event::listen('cms.page.beforeDisplay', function ((\Cms\Classes\Controller) $controller, (string) $url, (\Cms\Classes\Page) $page) {
         *         if ($url === '/tricked-you') {
         *             return \Cms\Classes\Page::loadCached('trick-theme-code', 'page-file-name');
         *         }
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.beforeDisplay', function ((string) $url, (\Cms\Classes\Page) $page) {
         *         if ($url === '/tricked-you') {
         *             return \Cms\Classes\Page::loadCached('trick-theme-code', 'page-file-name');
         *         }
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.beforeDisplay', [$url, $page])) {
            if ($event instanceof Page) {
                $page = $event;
            } else {
                return $event;
            }
        }

        /*
         * If the page was not found, render the 404 page - either provided by the theme or the built-in one.
         */
        if (!$page || $url === '404' || ($url === 'error' && !Config::get('app.debug', false))) {
            if (!Request::ajax()) {
                $this->setStatusCode(404);
            }

            // Log the 404 request
            if (!App::runningUnitTests()) {
                RequestLog::add();
            }

            if (!$page = $this->router->findByUrl('/404')) {
                return Response::make(View::make('cms::404'), $this->statusCode);
            }
        }

        /*
         * Run the page
         */
        $result = $this->runPage($page);

        /*
         * Post-processing
         */
        $result = $this->postProcessResult($page, $url, $result);

        /**
         * @event cms.page.display
         * Provides an opportunity to modify the response after the page for the URL has been processed. `$result` could be a string representing the HTML to be returned or it could be a Response instance.
         *
         * Example usage:
         *
         *     Event::listen('cms.page.display', function ((\Cms\Classes\Controller) $controller, (string) $url, (\Cms\Classes\Page) $page, (mixed) $result) {
         *         if ($url === '/tricked-you') {
         *             return Response::make('Boo!', 200);
         *         }
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.display', function ((string) $url, (\Cms\Classes\Page) $page, (mixed) $result) {
         *         if ($url === '/tricked-you') {
         *             return Response::make('Boo!', 200);
         *         }
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.display', [$url, $page, $result])) {
            $result = $event;
        }

        /*
         * Prepare and return response
         * @see \System\Traits\ResponseMaker
         */
        return $this->makeResponse($result);
    }

    /**
     * Renders a page in its entirety, including component initialization.
     * AJAX will be disabled for this process.
     *
     * @param string $pageFile Specifies the CMS page file name to run.
     * @param array  $parameters  Routing parameters.
     * @param \Cms\Classes\Theme  $theme  Theme object
     * @throws SystemException If the Page object or theme are unable to be found
     * @return mixed
     */
    public static function render($pageFile, $parameters = [], $theme = null)
    {
        if (!$theme && (!$theme = Theme::getActiveTheme())) {
            throw new SystemException(Lang::get('cms::lang.theme.active.not_found'));
        }

        $controller = new static($theme);
        $controller->getRouter()->setParameters($parameters);

        if (($page = Page::load($theme, $pageFile)) === null) {
            throw new SystemException(Lang::get('cms::lang.page.not_found_name', ['name'=>$pageFile]));
        }

        return $controller->runPage($page, false);
    }

    /**
     * Runs a page directly from its object and supplied parameters.
     *
     * @param \Cms\Classes\Page $page Specifies the CMS page to run.
     * @throws SystemException If the Layout object was unable to be found
     * @return mixed
     */
    public function runPage($page, $useAjax = true)
    {
        $this->page = $page;

        /*
         * If the page doesn't refer any layout, create the fallback layout.
         * Otherwise load the layout specified in the page.
         */
        if (!$page->layout) {
            $layout = Layout::initFallback($this->theme);
        }
        elseif (($layout = Layout::loadCached($this->theme, $page->layout)) === null) {
            throw new SystemException(Lang::get('cms::lang.layout.not_found_name', ['name'=>$page->layout]));
        }

        $this->layout = $layout;

        /*
         * The 'this' variable is reserved for default variables.
         */
        $this->getTwig()->addGlobal('this', $this->getControllerGlobalVars());

        /*
         * Add global vars defined by View::share() into Twig, only if they have yet to be specified.
         */
        $globalVars = ViewHelper::getGlobalVars();
        if (!empty($globalVars)) {
            $existingGlobals = array_keys($this->getTwig()->getGlobals());
            foreach ($globalVars as $key => $value) {
                if (!in_array($key, $existingGlobals)) {
                    $this->getTwig()->addGlobal($key, $value);
                }
            }
        }

        /*
         * Check for the presence of validation errors in the session.
         */
        $this->vars['errors'] = (Config::get('session.driver') && Session::has('errors'))
            ? Session::get('errors')
            : new \Illuminate\Support\ViewErrorBag;

        /*
         * Handle AJAX requests and execute the life cycle functions
         */
        $this->initCustomObjects();

        $this->initComponents();

        /*
         * Give the layout and page an opportunity to participate
         * after components are initialized and before AJAX is handled.
         */
        if ($this->layoutObj) {
            CmsException::mask($this->layout, 300);
            $this->layoutObj->onInit();
            CmsException::unmask();
        }

        CmsException::mask($this->page, 300);
        $this->pageObj->onInit();
        CmsException::unmask();

        /**
         * @event cms.page.init
         * Provides an opportunity to return a custom response from Controller->runPage() before AJAX handlers are executed
         *
         * Example usage:
         *
         *     Event::listen('cms.page.init', function ((\Cms\Classes\Controller) $controller, (\Cms\Classes\Page) $page) {
         *         return \Cms\Classes\Page::loadCached('trick-theme-code', 'page-file-name');
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.init', function ((\Cms\Classes\Page) $page) {
         *         return \Cms\Classes\Page::loadCached('trick-theme-code', 'page-file-name');
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.init', [$page])) {
            return $event;
        }

        /*
         * Execute AJAX event
         */
        if ($useAjax && $ajaxResponse = $this->execAjaxHandlers()) {
            return $ajaxResponse;
        }

        /*
         * Execute postback handler
         */
        if (
            $useAjax &&
            ($handler = post('_handler')) &&
            $this->verifyCsrfToken() &&
            ($handlerResponse = $this->runAjaxHandler($handler)) &&
            $handlerResponse !== true
        ) {
            return $handlerResponse;
        }

        /*
         * Execute page lifecycle
         */
        if ($cycleResponse = $this->execPageCycle()) {
            return $cycleResponse;
        }

        /**
         * @event cms.page.beforeRenderPage
         * Fires after AJAX handlers are dealt with and provides an opportunity to modify the page contents
         *
         * Example usage:
         *
         *     Event::listen('cms.page.beforeRenderPage', function ((\Cms\Classes\Controller) $controller, (\Cms\Classes\Page) $page) {
         *         return 'Custom page contents';
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.beforeRenderPage', function ((\Cms\Classes\Page) $page) {
         *         return 'Custom page contents';
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.beforeRenderPage', [$page])) {
            $this->pageContents = $event;
        }
        else {
            /*
             * Render the page
             */
            CmsException::mask($this->page, 400);
            $this->getLoader()->setObject($this->page);
            $template = $this->getTwig()->load($this->page->getFilePath());
            $this->pageContents = $template->render($this->vars);
            CmsException::unmask();
        }

        /*
         * Render the layout
         */
        CmsException::mask($this->layout, 400);
        $this->getLoader()->setObject($this->layout);
        $template = $this->getTwig()->load($this->layout->getFilePath());
        $result = $template->render($this->vars);
        CmsException::unmask();

        return $result;
    }

    /**
     * Invokes the current page cycle without rendering the page,
     * used by AJAX handler that may rely on the logic inside the action.
     */
    public function pageCycle()
    {
        return $this->execPageCycle();
    }

    /**
     * Executes the page life cycle.
     * Creates an object from the PHP sections of the page and
     * it's layout, then executes their life cycle functions.
     */
    protected function execPageCycle()
    {
        /**
         * @event cms.page.start
         * Fires before all of the page & layout lifecycle handlers are run
         *
         * Example usage:
         *
         *     Event::listen('cms.page.start', function ((\Cms\Classes\Controller) $controller) {
         *         return Response::make('Taking over the lifecycle!', 200);
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.start', function () {
         *         return Response::make('Taking over the lifecycle!', 200);
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.start')) {
            return $event;
        }

        /*
         * Run layout functions
         */
        if ($this->layoutObj) {
            CmsException::mask($this->layout, 300);
            $response = (
                ($result = $this->layoutObj->onStart()) ||
                ($result = $this->layout->runComponents()) ||
                ($result = $this->layoutObj->onBeforePageStart())
            ) ? $result : null;
            CmsException::unmask();

            if ($response) {
                return $response;
            }
        }

        /*
         * Run page functions
         */
        CmsException::mask($this->page, 300);
        $response = (
            ($result = $this->pageObj->onStart()) ||
            ($result = $this->page->runComponents()) ||
            ($result = $this->pageObj->onEnd())
        ) ? $result : null;
        CmsException::unmask();

        if ($response) {
            return $response;
        }

        /*
         * Run remaining layout functions
         */
        if ($this->layoutObj) {
            CmsException::mask($this->layout, 300);
            $response = ($result = $this->layoutObj->onEnd()) ? $result : null;
            CmsException::unmask();
        }

        /**
         * @event cms.page.end
         * Fires after all of the page & layout lifecycle handlers are run
         *
         * Example usage:
         *
         *     Event::listen('cms.page.end', function ((\Cms\Classes\Controller) $controller) {
         *         return Response::make('Taking over the lifecycle!', 200);
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.end', function () {
         *         return Response::make('Taking over the lifecycle!', 200);
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.end')) {
            return $event;
        }

        return $response;
    }

    /**
     * Post-processes page HTML code before it's sent to the client.
     * Note for pre-processing see cms.template.processTwigContent event.
     * @param \Cms\Classes\Page $page Specifies the current CMS page.
     * @param string $url Specifies the current URL.
     * @param string $content The page markup to post-process.
     * @return string Returns the updated result string.
     */
    protected function postProcessResult($page, $url, $content)
    {
        $content = MediaViewHelper::instance()->processHtml($content);

        /**
         * @event cms.page.postprocess
         * Provides opportunity to hook into the post-processing of page HTML code before being sent to the client. `$dataHolder` = {content: $htmlContent}
         *
         * Example usage:
         *
         *     Event::listen('cms.page.postprocess', function ((\Cms\Classes\Controller) $controller, (string) $url, (\Cms\Classes\Page) $page, (object) $dataHolder) {
         *         return 'My custom content';
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.postprocess', function ((string) $url, (\Cms\Classes\Page) $page, (object) $dataHolder) {
         *         return 'My custom content';
         *     });
         *
         */
        $dataHolder = (object) ['content' => $content];
        $this->fireSystemEvent('cms.page.postprocess', [$url, $page, $dataHolder]);

        return $dataHolder->content;
    }

    //
    // Initialization
    //

    /**
     * Initializes the Twig environment and loader.
     * Registers the \Cms\Twig\Extension object with Twig.
     * @return void
     */
    protected function initTwigEnvironment()
    {
        $this->twig = App::make('twig.environment.cms');
        $this->twig->getExtension(\Cms\Twig\Extension::class)->setController($this);
    }

    /**
     * Initializes the custom layout and page objects.
     * @return void
     */
    protected function initCustomObjects()
    {
        $this->layoutObj = null;

        if (!$this->layout->isFallBack()) {
            CmsException::mask($this->layout, 300);
            $parser = new CodeParser($this->layout);
            $this->layoutObj = $parser->source($this->page, $this->layout, $this);
            CmsException::unmask();
        }

        CmsException::mask($this->page, 300);
        $parser = new CodeParser($this->page);
        $this->pageObj = $parser->source($this->page, $this->layout, $this);
        CmsException::unmask();
    }

    /**
     * Initializes the components for the layout and page.
     * @return void
     */
    protected function initComponents()
    {
        if (!$this->layout->isFallBack()) {
            foreach ($this->layout->settings['components'] as $component => $properties) {
                list($name, $alias) = strpos($component, ' ')
                    ? explode(' ', $component)
                    : [$component, $component];

                $this->addComponent($name, $alias, $properties, true);
            }
        }

        foreach ($this->page->settings['components'] as $component => $properties) {
            list($name, $alias) = strpos($component, ' ')
                ? explode(' ', $component)
                : [$component, $component];

            $this->addComponent($name, $alias, $properties);
        }

        /**
         * @event cms.page.initComponents
         * Fires after the components for the given page have been initialized
         *
         * Example usage:
         *
         *     Event::listen('cms.page.initComponents', function ((\Cms\Classes\Controller) $controller, (\Cms\Classes\Page) $page, (\Cms\Classes\Layout) $layout) {
         *         \Log::info($page->title . ' components have been initialized');
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.initComponents', function ((\Cms\Classes\Page) $page, (\Cms\Classes\Layout) $layout) {
         *         \Log::info($page->title . ' components have been initialized');
         *     });
         *
         */
        $this->fireSystemEvent('cms.page.initComponents', [$this->page, $this->layout]);
    }

    //
    // AJAX
    //

    /**
     * Returns the AJAX handler for the current request, if available.
     * @return string
     */
    public function getAjaxHandler()
    {
        if (!Request::ajax() || Request::method() != 'POST') {
            return null;
        }

        if ($handler = Request::header('X_WINTER_REQUEST_HANDLER')) {
            return trim($handler);
        }

        return null;
    }

    /**
     * Executes the page, layout, component and plugin AJAX handlers.
     *
     * @throws SystemException If the handler is invalid or could not be found
     * @return mixed Returns the AJAX Response object or null.
     */
    protected function execAjaxHandlers()
    {
        if ($handler = $this->getAjaxHandler()) {
            try {
                /*
                 * Validate the handler name
                 */
                if (!preg_match('/^(?:\w+\:{2})?on[A-Z]{1}[\w+]*$/', $handler)) {
                    throw new SystemException(Lang::get('cms::lang.ajax_handler.invalid_name', ['name'=>$handler]), 400);
                }

                /*
                 * Validate the handler partial list
                 */
                if ($partialList = trim(Request::header('X_WINTER_REQUEST_PARTIALS'))) {
                    $partialList = explode('&', $partialList);

                    foreach ($partialList as $partial) {
                        if (!preg_match('/^(?:\w+\:{2}|@)?[a-z0-9\_\-\.\/]+$/i', $partial)) {
                            throw new SystemException(Lang::get('cms::lang.partial.invalid_name', ['name'=>$partial]), 400);
                        }
                    }
                }
                else {
                    $partialList = [];
                }

                $responseContents = [];

                /*
                 * Execute the handler
                 */
                if (!$result = $this->runAjaxHandler($handler)) {
                    throw new SystemException(Lang::get('cms::lang.ajax_handler.not_found', ['name'=>$handler]));
                }

                /*
                 * Render partials and return the response as array that will be converted to JSON automatically.
                 */
                foreach ($partialList as $partial) {
                    $responseContents[$partial] = $this->renderPartial($partial);
                }

                /*
                 * If the handler returned a redirect, process the URL and dispose of it so
                 * framework.js knows to redirect the browser and not the request!
                 */
                if ($result instanceof RedirectResponse) {
                    $responseContents['X_WINTER_REDIRECT'] = $result->getTargetUrl();
                    $result = null;
                }
                /*
                 * No redirect is used, look for any flash messages
                 */
                elseif (Request::header('X_WINTER_REQUEST_FLASH') && Flash::check()) {
                    $responseContents['X_WINTER_FLASH_MESSAGES'] = Flash::all();
                }

                /*
                 * If the handler returned an array, we should add it to output for rendering.
                 * If it is a string, add it to the array with the key "result".
                 * If an object, pass it to Laravel as a response object.
                 */
                if (is_array($result)) {
                    $responseContents = array_merge($responseContents, $result);
                }
                elseif (is_string($result)) {
                    $responseContents['result'] = $result;
                }
                elseif (is_object($result)) {
                    return $result;
                }

                return Response::make($responseContents, $this->statusCode);
            }
            catch (ValidationException $ex) {
                /*
                 * Handle validation errors
                 */
                $responseContents['X_WINTER_ERROR_FIELDS'] = $ex->getFields();
                $responseContents['X_WINTER_ERROR_MESSAGE'] = $ex->getMessage();
                throw new AjaxException($responseContents);
            }
            catch (Exception $ex) {
                throw $ex;
            }
        }

        return null;
    }

    /**
     * Tries to find and run an AJAX handler in the page, layout, components and plugins.
     * The method stops as soon as the handler is found.
     * @param string $handler name of the ajax handler
     * @return boolean Returns true if the handler was found. Returns false otherwise.
     */
    protected function runAjaxHandler($handler)
    {
        /**
         * @event cms.ajax.beforeRunHandler
         * Provides an opportunity to modify an AJAX request
         *
         * The parameter provided is `$handler` (the requested AJAX handler to be run)
         *
         * Example usage (forwards AJAX handlers to a backend widget):
         *
         *     Event::listen('cms.ajax.beforeRunHandler', function ((\Cms\Classes\Controller) $controller, (string) $handler) {
         *         if (strpos($handler, '::')) {
         *             list($componentAlias, $handlerName) = explode('::', $handler);
         *             if ($componentAlias === $this->getBackendWidgetAlias()) {
         *                 return $this->backendControllerProxy->runAjaxHandler($handler);
         *             }
         *         }
         *     });
         *
         * Or
         *
         *     $this->controller->bindEvent('ajax.beforeRunHandler', function ((string) $handler) {
         *         if (strpos($handler, '::')) {
         *             list($componentAlias, $handlerName) = explode('::', $handler);
         *             if ($componentAlias === $this->getBackendWidgetAlias()) {
         *                 return $this->backendControllerProxy->runAjaxHandler($handler);
         *             }
         *         }
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.ajax.beforeRunHandler', [$handler])) {
            return $event;
        }

        /*
         * Process Component handler
         */
        if (strpos($handler, '::')) {
            list($componentName, $handlerName) = explode('::', $handler);
            $componentObj = $this->findComponentByName($componentName);

            if ($componentObj && $componentObj->methodExists($handlerName)) {
                $this->componentContext = $componentObj;
                $result = $componentObj->runAjaxHandler($handlerName);
                return $result ?: true;
            }
        }
        /*
         * Process code section handler
         */
        else {
            if (method_exists($this->pageObj, $handler)) {
                $result = $this->pageObj->$handler();
                return $result ?: true;
            }

            if (!$this->layout->isFallBack() && method_exists($this->layoutObj, $handler)) {
                $result = $this->layoutObj->$handler();
                return $result ?: true;
            }

            /*
             * Cycle each component to locate a usable handler
             */
            if (($componentObj = $this->findComponentByHandler($handler)) !== null) {
                $this->componentContext = $componentObj;
                $result = $componentObj->runAjaxHandler($handler);
                return $result ?: true;
            }
        }

        /*
         * Generic handler that does nothing
         */
        if ($handler == 'onAjax') {
            return true;
        }

        return false;
    }

    //
    // Rendering
    //

    /**
     * Renders a requested page.
     * The framework uses this method internally.
     */
    public function renderPage()
    {
        $contents = $this->pageContents;

        /**
         * @event cms.page.render
         * Provides an opportunity to manipulate the page's rendered contents
         *
         * Example usage:
         *
         *     Event::listen('cms.page.render', function ((\Cms\Classes\Controller) $controller, (string) $pageContents) {
         *         return 'My custom contents';
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.render', function ((string) $pageContents) {
         *         return 'My custom contents';
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.render', [$contents])) {
            return $event;
        }

        return $contents;
    }

    /**
     * Renders a requested partial.
     * The framework uses this method internally.
     *
     * @param string $name The view to load.
     * @param array $parameters Parameter variables to pass to the view.
     * @param bool $throwException Throw an exception if the partial is not found.
     * @throws SystemException If the partial cannot be found
     * @return mixed Partial contents or false if not throwing an exception.
     */
    public function renderPartial($name, $parameters = [], $throwException = true)
    {
        $vars = $this->vars;
        $this->vars = array_merge($this->vars, $parameters);

        /*
         * Alias @ symbol for ::
         */
        if (substr($name, 0, 1) == '@') {
            $name = '::' . substr($name, 1);
        }

        /**
         * @event cms.page.beforeRenderPartial
         * Provides an opportunity to manipulate the name of the partial being rendered before it renders
         *
         * Example usage:
         *
         *     Event::listen('cms.page.beforeRenderPartial', function ((\Cms\Classes\Controller) $controller, (string) $partialName) {
         *         return Cms\Classes\Partial::loadCached($theme, 'custom-partial-name');
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.beforeRenderPartial', function ((string) $partialName) {
         *         return Cms\Classes\Partial::loadCached($theme, 'custom-partial-name');
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.beforeRenderPartial', [$name])) {
            $partial = $event;
        }
        /*
         * Process Component partial
         */
        elseif (strpos($name, '::') !== false) {
            list($componentAlias, $partialName) = explode('::', $name);

            /*
             * Component alias not supplied
             */
            if (!strlen($componentAlias)) {
                if ($this->componentContext !== null) {
                    $componentObj = $this->componentContext;
                }
                elseif (($componentObj = $this->findComponentByPartial($partialName)) === null) {
                    if ($throwException) {
                        throw new SystemException(Lang::get('cms::lang.partial.not_found_name', ['name'=>$partialName]));
                    }

                    return false;
                }
            }
            /*
             * Component alias is supplied
             */
            elseif (($componentObj = $this->findComponentByName($componentAlias)) === null) {
                if ($throwException) {
                    throw new SystemException(Lang::get('cms::lang.component.not_found', ['name'=>$componentAlias]));
                }

                return false;
            }

            $partial = null;
            $this->componentContext = $componentObj;

            /*
             * Check if the theme has an override
             */
            $partial = ComponentPartial::loadOverrideCached($this->theme, $componentObj, $partialName);

            /*
             * Check the component partial
             */
            if ($partial === null) {
                $partial = ComponentPartial::loadCached($componentObj, $partialName);
            }

            if ($partial === null) {
                if ($throwException) {
                    throw new SystemException(Lang::get('cms::lang.partial.not_found_name', ['name'=>$name]));
                }

                return false;
            }

            /*
             * Set context for self access
             */
            $this->vars['__SELF__'] = $componentObj;
        }
        /*
         * Process theme partial
         */
        elseif (($partial = Partial::loadCached($this->theme, $name)) === null) {
            if ($throwException) {
                throw new SystemException(Lang::get('cms::lang.partial.not_found_name', ['name'=>$name]));
            }

            return false;
        }

        /*
         * Run functions for CMS partials only (Cms\Classes\Partial)
         */
        if ($partial instanceof Partial) {
            $this->partialStack->stackPartial();

            $manager = ComponentManager::instance();

            foreach ($partial->settings['components'] as $component => $properties) {
                // Do not inject the viewBag component to the environment.
                // Not sure if they're needed there by the requirements,
                // but there were problems with array-typed properties used by Static Pages
                // snippets and setComponentPropertiesFromParams(). --ab
                if ($component == 'viewBag') {
                    continue;
                }

                list($name, $alias) = strpos($component, ' ')
                    ? explode(' ', $component)
                    : [$component, $component];

                if (!$componentObj = $manager->makeComponent($name, $this->pageObj, $properties)) {
                    throw new SystemException(Lang::get('cms::lang.component.not_found', ['name'=>$name]));
                }

                $componentObj->alias = $alias;
                $parameters[$alias] = $partial->components[$alias] = $componentObj;

                $this->partialStack->addComponent($alias, $componentObj);

                $this->setComponentPropertiesFromParams($componentObj, $parameters);
                $componentObj->init();
            }

            CmsException::mask($this->page, 300);
            $parser = new CodeParser($partial);
            $partialObj = $parser->source($this->page, $this->layout, $this);
            CmsException::unmask();

            CmsException::mask($partial, 300);
            $partialObj->onStart();
            $partial->runComponents();
            $partialObj->onEnd();
            CmsException::unmask();
        }

        /*
         * Render the partial
         */
        CmsException::mask($partial, 400);
        $this->getLoader()->setObject($partial);
        $template = $this->getTwig()->load($partial->getFilePath());
        $partialContent = $template->render(array_merge($parameters, $this->vars));
        CmsException::unmask();

        if ($partial instanceof Partial) {
            $this->partialStack->unstackPartial();
        }

        $this->vars = $vars;

        /**
         * @event cms.page.renderPartial
         * Provides an opportunity to manipulate the output of a partial after being rendered
         *
         * Example usage:
         *
         *     Event::listen('cms.page.renderPartial', function ((\Cms\Classes\Controller) $controller, (string) $partialName, (string) &$partialContent) {
         *         return "Overriding content";
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.renderPartial', function ((string) $partialName, (string) &$partialContent) {
         *         return "Overriding content";
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.renderPartial', [$name, &$partialContent])) {
            return $event;
        }

        return $partialContent;
    }

    /**
     * Renders a requested content file.
     * The framework uses this method internally.
     *
     * @param string $name The content view to load.
     * @param array $parameters Parameter variables to pass to the view.
     * @param bool $throwException Throw an exception if the content file is not found.
     * @throws SystemException If the content cannot be found, and `$throwException` is true.
     * @return string|false Content file, or false if `$throwException` is false.
     */
    public function renderContent($name, $parameters = [], $throwException = true)
    {
        /**
         * @event cms.page.beforeRenderContent
         * Provides an opportunity to manipulate the name of the content file being rendered before it renders
         *
         * Example usage:
         *
         *     Event::listen('cms.page.beforeRenderContent', function ((\Cms\Classes\Controller) $controller, (string) $contentName) {
         *         return Cms\Classes\Content::loadCached($theme, 'custom-content-name');
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.beforeRenderContent', function ((string) $contentName) {
         *         return Cms\Classes\Content::loadCached($theme, 'custom-content-name');
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.beforeRenderContent', [$name])) {
            $content = $event;
        }
        /*
         * Load content from theme
         */
        elseif (($content = Content::loadCached($this->theme, $name)) === null) {
            if ($throwException) {
                throw new SystemException(Lang::get('cms::lang.content.not_found_name', ['name' => $name]));
            } else {
                return false;
            }
        }

        $fileContent = $content->parsedMarkup;

        /*
         * Inject global view variables
         */
        $globalVars = ViewHelper::getGlobalVars();
        if (!empty($globalVars)) {
            $parameters = (array) $parameters + $globalVars;
        }

        /*
         * Parse basic template variables
         */
        if (!empty($parameters)) {
            $fileContent = TextParser::parse($fileContent, $parameters);
        }

        /**
         * @event cms.page.renderContent
         * Provides an opportunity to manipulate the output of a content file after being rendered
         *
         * Example usage:
         *
         *     Event::listen('cms.page.renderContent', function ((\Cms\Classes\Controller) $controller, (string) $contentName, (string) &$fileContent) {
         *         return "Overriding content";
         *     });
         *
         * Or
         *
         *     $CmsController->bindEvent('page.renderContent', function ((string) $contentName, (string) &$fileContent) {
         *         return "Overriding content";
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('cms.page.renderContent', [$name, &$fileContent])) {
            return $event;
        }

        return $fileContent;
    }

    /**
     * Renders a component's default content, preserves the previous component context.
     * @param $name
     * @param array $parameters
     * @return string Returns the component default contents.
     */
    public function renderComponent($name, $parameters = [])
    {
        $result = null;
        $previousContext = $this->componentContext;

        if ($componentObj = $this->findComponentByName($name)) {
            $componentObj->id = uniqid($name);
            $componentObj->setProperties(array_merge($componentObj->getProperties(), $parameters));
            $this->componentContext = $componentObj;
            $result = $componentObj->onRender();
        }

        if (!$result) {
            $result = $this->renderPartial($name.'::default', [], false);
        }

        $this->componentContext = $previousContext;
        return $result;
    }

    //
    // Getters
    //

    /**
     * Returns an existing instance of the controller.
     * If the controller doesn't exists, returns null.
     */
    public static function getController(): ?self
    {
        return self::$instance;
    }

    /**
     * Returns the current CMS theme.
     * @return \Cms\Classes\Theme
     */
    public function getTheme()
    {
        return $this->theme;
    }

    /**
     * Returns the Twig environment.
     * @return TwigEnvironment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * Returns the globals to be provided to twig
     */
    public function getControllerGlobalVars()
    {
        return [
            'page'        => $this->page ?? new Page(),
            'layout'      => $this->layout ?? new Layout(),
            'theme'       => $this->theme,
            'param'       => $this->router->getParameters(),
            'controller'  => $this,
            'environment' => App::environment(),
            'session'     => App::make('session'),
        ];
    }

    /**
     * Returns the Twig loader.
     * @return \Cms\Twig\Loader
     */
    public function getLoader()
    {
        return $this->getTwig()->getLoader();
    }

    /**
     * Returns the routing object.
     * @return \Cms\Classes\Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Intended to be called from the layout, returns the page code base object.
     * @return \Cms\Classes\CodeBase
     */
    public function getPageObject()
    {
        return $this->pageObj;
    }

    /**
     * Returns the CMS page object being processed by the controller.
     * The object is not available on the early stages of the controller
     * initialization.
     * @return \Cms\Classes\Page Returns the Page object or null.
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Intended to be called from the page, returns the layout code base object.
     * @return \Cms\Classes\CodeBase
     */
    public function getLayoutObject()
    {
        return $this->layoutObj;
    }

    /**
     * Returns the CMS layout object being processed by the controller.
     * The object is not available on the early stages of the controller
     * initialization.
     * @return \Cms\Classes\Layout Returns the Layout object or null.
     */
    public function getLayout()
    {
        return $this->layout;
    }

    //
    // Page helpers
    //

    /**
     * Looks up the URL for a supplied page and returns it relative to the website root.
     *
     * @param mixed $name Specifies the Cms Page file name.
     * @param array|bool $parameters Route parameters to consider in the URL. If boolean will be used as the value for $routePersistence
     * @param bool $routePersistence By default the existing routing parameters will be included
     * @return string|null
     */
    public function pageUrl($name, $parameters = [], $routePersistence = true)
    {
        if (!$name) {
            return $this->currentPageUrl($parameters, $routePersistence);
        }

        /*
         * Second parameter can act as third
         */
        if (is_bool($parameters)) {
            $routePersistence = $parameters;
        }

        if (!is_array($parameters)) {
            $parameters = [];
        }

        if ($routePersistence) {
            $parameters = array_merge($this->router->getParameters(), $parameters);
        }

        if (!$url = $this->router->findByFile($name, $parameters)) {
            return null;
        }

        return Cms::url($url);
    }

    /**
     * Looks up the current page URL with supplied parameters and route persistence.
     * @param array $parameters
     * @param bool $routePersistence
     * @return null|string
     */
    public function currentPageUrl($parameters = [], $routePersistence = true)
    {
        if (!$currentFile = $this->page->getFileName()) {
            return null;
        }

        return $this->pageUrl($currentFile, $parameters, $routePersistence);
    }

    /**
     * Converts supplied URL to a theme URL relative to the website root. If the URL provided is an
     * array then the files will be combined.
     * @param mixed $url Specifies the theme-relative URL. If null, the theme path is returned.
     * @return string
     */
    public function themeUrl($url = null): string
    {
        return is_array($url)
            ? $this->themeCombineAssets($url)
            : $this->getTheme()->assetUrl($url);
    }

    /**
     * Generates a URL to the AssetCombiner for the provided array of assets
     */
    protected function themeCombineAssets(array $url): string
    {
        $themeDir = $this->getTheme()->getDirName();
        $parentTheme = $this->getTheme()->getConfig()['parent'] ?? false;
        $themesPath = themes_path();

        $cacheKey = __METHOD__ . '.' . md5(json_encode($url));

        if (!($assets = Cache::get($cacheKey))) {
            $assets = [];
            $sources = [
                $themesPath . '/' . $themeDir
            ];

            if ($parentTheme) {
                $sources[] = $themesPath . '/' . $parentTheme;
            }

            foreach ($url as $file) {
                // Leave Combiner Aliases assets & absolute path assets (using path symbols like $, #, ~) unmodified
                if (str_starts_with($file, '@') || File::isPathSymbol($file)) {
                    $assets[] = $file;
                    continue;
                }

                foreach ($sources as $source) {
                    $asset = $source . '/' . $file;
                    if (File::exists($asset)) {
                        $assets[] = $asset;
                        break;
                    } else {
                        $asset = null;
                    }
                }
                if (is_null($asset)) {
                    // Skip combining missing assets and log an error
                    Log::error("$file could not be found in any of the theme's sources (" . implode(', ', $sources) . ")");
                    continue;
                }
            }

            Cache::put($cacheKey, $assets);
        }

        return Url::to(CombineAssets::combine($assets));
    }

    /**
     * Returns a routing parameter.
     * @param string $name Routing parameter name.
     * @param string $default Default to use if none is found.
     * @return string
     */
    public function param($name, $default = null)
    {
        return $this->router->getParameter($name, $default);
    }

    //
    // Component helpers
    //

    /**
     * Adds a component to the page object.
     *
     * @param mixed $name Component class name or short name
     * @param string $alias Alias to give the component
     * @param array $properties Component properties
     * @param bool $addToLayout Add to layout, instead of page
     * @throws SystemException if the (hard) component class is not found or is not registered.
     * @return ComponentBase|null Component object. Will return `null` if a soft component is used but not found.
     */
    public function addComponent($name, $alias, $properties, $addToLayout = false)
    {
        $manager = ComponentManager::instance();
        $isSoftComponent = $this->isSoftComponent($name);

        if ($isSoftComponent) {
            $name = $this->parseComponentLabel($name);
            $alias = $this->parseComponentLabel($alias);
        }

        $componentObj = $manager->makeComponent(
            $name,
            ($addToLayout) ? $this->layoutObj : $this->pageObj,
            $properties,
            $isSoftComponent
        );

        if (is_null($componentObj)) {
            if (!$isSoftComponent) {
                throw new SystemException(Lang::get('cms::lang.component.not_found', ['name' => $name]));
            }

            // A missing soft component will return null.
            return null;
        }

        $componentObj->alias = $alias;
        $this->vars[$alias] = $componentObj;

        if ($addToLayout) {
            $this->layout->components[$alias] = $componentObj;
        } else {
            $this->page->components[$alias] = $componentObj;
        }

        $this->setComponentPropertiesFromParams($componentObj);
        $componentObj->init();

        return $componentObj;
    }

    /**
     * Searches the layout and page components by an alias
     * @param $name
     * @return ComponentBase The component object, if found
     */
    public function findComponentByName($name)
    {
        if (isset($this->page->components[$name])) {
            return $this->page->components[$name];
        }

        if (isset($this->layout->components[$name])) {
            return $this->layout->components[$name];
        }

        $partialComponent = $this->partialStack->getComponent($name);
        if ($partialComponent !== null) {
            return $partialComponent;
        }

        return null;
    }

    /**
     * Searches the layout and page components by an AJAX handler
     * @param string $handler
     * @return ComponentBase The component object, if found
     */
    public function findComponentByHandler($handler)
    {
        foreach ($this->page->components as $component) {
            if ($component->methodExists($handler)) {
                return $component;
            }
        }

        foreach ($this->layout->components as $component) {
            if ($component->methodExists($handler)) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Searches the layout and page components by a partial file
     * @param string $partial
     * @return ComponentBase|null The component object, if found
     */
    public function findComponentByPartial($partial)
    {
        foreach ($this->page->components as $component) {
            if (ComponentPartial::check($component, $partial)) {
                return $component;
            }
        }

        foreach ($this->layout->components as $component) {
            if (ComponentPartial::check($component, $partial)) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Set the component context manually, used by Components when calling renderPartial.
     * @param ComponentBase $component
     * @return void
     */
    public function setComponentContext(ComponentBase $component = null)
    {
        $this->componentContext = $component;
    }

    /**
     * Sets component property values from partial parameters.
     * The property values should be defined as {{ param }}.
     * @param ComponentBase $component The component object.
     * @param array $parameters Specifies the partial parameters.
     */
    protected function setComponentPropertiesFromParams($component, $parameters = [])
    {
        $properties = $component->getProperties();
        $routerParameters = $this->router->getParameters();

        foreach ($properties as $propertyName => $propertyValue) {
            if (is_array($propertyValue)) {
                continue;
            }

            $matches = [];
            if (preg_match('/^\{\{([^\}]+)\}\}$/', $propertyValue, $matches)) {
                $paramName = trim($matches[1]);

                if (substr($paramName, 0, 1) == ':') {
                    $routeParamName = substr($paramName, 1);
                    $newPropertyValue = $routerParameters[$routeParamName] ?? null;
                }
                else {
                    $newPropertyValue = array_get($parameters, $paramName, null);
                }

                $component->setProperty($propertyName, $newPropertyValue);
                $component->setExternalPropertyName($propertyName, $paramName);
            }
        }
    }

    /**
     * Removes prefixed '@' from soft component name
     * @param string $label
     * @return string
     */
    protected function parseComponentLabel($label)
    {
        if ($this->isSoftComponent($label)) {
            return ltrim($label, '@');
        }
        return $label;
    }

    /**
     * Checks if component name has @.
     * @param string $label
     * @return bool
     */
    protected function isSoftComponent($label)
    {
        return starts_with($label, '@');
    }
}
