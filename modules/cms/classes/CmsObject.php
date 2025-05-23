<?php namespace Cms\Classes;

use App;
use Lang;
use Event;
use Config;
use Exception;
use ValidationException;
use ApplicationException;
use Cms\Contracts\CmsObject as CmsObjectContract;
use Winter\Storm\Filesystem\PathResolver;
use Winter\Storm\Halcyon\Model as HalcyonModel;

/**
 * This is a base class for all CMS objects - content files, pages, partials and layouts.
 * The class implements basic operations with file-based templates.
 *
 * @package winter\wn-cms-module
 * @author Alexey Bobkov, Samuel Georges
 */
class CmsObject extends HalcyonModel implements CmsObjectContract
{
    use \Winter\Storm\Halcyon\Traits\Validation;

    /**
     * @var array The rules to be applied to the data.
     */
    public $rules = [];

    /**
     * @var array The array of custom attribute names.
     */
    public $attributeNames = [];

    /**
     * @var array The array of custom error messages.
     */
    public $customMessages = [];

    /**
     * @var int The maximum allowed path nesting level. The default value is 2,
     * meaning that files can only exist in the root directory, or in a
     * subdirectory. Set to null if any level is allowed.
     */
    protected $maxNesting = null;

    /**
     * @var array The attributes that are mass assignable.
     */
    protected $fillable = [
        'content'
    ];

    /**
     * @var bool Model supports code and settings sections.
     */
    protected $isCompoundObject = false;

    /**
     * @var \Cms\Classes\Theme A reference to the CMS theme containing the object.
     */
    protected $themeCache;

    /**
     * The "booting" method of the model.
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        static::bootDefaultTheme();
    }

    /**
     * Boot all of the bootable traits on the model.
     * @return void
     */
    protected static function bootDefaultTheme()
    {
        $resolver = static::getDatasourceResolver();
        if ($resolver->getDefaultDatasource()) {
            return;
        }

        $defaultTheme = App::runningInBackend()
            ? Theme::getEditThemeCode()
            : Theme::getActiveThemeCode();

        Theme::load($defaultTheme);

        $resolver->setDefaultDatasource($defaultTheme);
    }

    /**
     * Loads the object from a file.
     * This method is used in the CMS back-end. It doesn't use any caching.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name, with the extension.
     * The file name can contain only alphanumeric symbols, dashes and dots.
     * @return mixed Returns a CMS object instance or null if the object wasn't found.
     */
    public static function load($theme, $fileName)
    {
        return static::inTheme($theme)->find($fileName);
    }

    /**
     * Loads the object from a cache.
     * This method is used by the CMS in the runtime. If the cache is not found, it is created.
     * @param \Cms\Classes\Theme $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name, with the extension.
     * @return static|null Returns a CMS object instance or null if the object wasn't found.
     */
    public static function loadCached($theme, $fileName)
    {
        return static::inTheme($theme)
            ->remember(Config::get('cms.parsedPageCacheTTL', 1440))
            ->find($fileName)
        ;
    }

    /**
     * Returns the list of objects in the specified theme.
     * This method is used internally by the system.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @param boolean $skipCache Indicates if objects should be reloaded from the disk bypassing the cache.
     * @return CmsObjectCollection Returns a collection of CMS objects.
     */
    public static function listInTheme($theme, $skipCache = false)
    {
        $result = [];
        $instance = static::inTheme($theme);

        if ($skipCache) {
            $result = $instance->get();
        } else {
            $items = $instance->newQuery()->lists('fileName');

            $loadedItems = [];
            foreach ($items as $item) {
                $loaded = static::loadCached($theme, $item);
                if ($loaded) {
                    $loadedItems[] = $loaded;
                }
                unset($loaded);
            }

            $result = $instance->newCollection($loadedItems);
        }

        /**
         * @event cms.object.listInTheme
         * Provides opportunity to filter the items returned by a call to CmsObject::listInTheme()
         *
         * Parameters provided are `$cmsObject` (the object being listed) and `$objectList` (a collection of the CmsObjects being returned).
         * > Note: The `$objectList` provided is an object reference to a CmsObjectCollection, to make changes you must use object modifying methods.
         *
         * Example usage (filters all pages except for the 404 page on the CMS Maintenance mode settings page):
         *
         *     // Extend only the Settings Controller
         *     \System\Controllers\Settings::extend(function ($controller) {
         *         // Listen for the cms.object.listInTheme event
         *         \Event::listen('cms.object.listInTheme', function ($cmsObject, $objectList) {
         *             // Get the current context of the Settings Manager to ensure we only affect what we need to affect
         *             $context = \System\Classes\SettingsManager::instance()->getContext();
         *             if ($context->owner === 'winter.cms' && $context->itemCode === 'maintenance_settings') {
         *                 // Double check that this is a Page List that we're modifying
         *                 if ($cmsObject instanceof \Cms\Classes\Page) {
         *                     // Perform filtering with an original-object modifying method as $objectList is passed by reference (being that it's an object)
         *                     foreach ($objectList as $index => $page) {
         *                         if ($page->url !== '/404') {
         *                             $objectList->forget($index);
         *                         }
         *                     }
         *                 }
         *             }
         *         });
         *     });
         */
        Event::fire('cms.object.listInTheme', [$instance, $result]);

        return $result;
    }

    /**
     * Prepares the theme datasource for the model.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @return static
     */
    public static function inTheme($theme)
    {
        if (is_string($theme)) {
            $theme = Theme::load($theme);
        }

        return static::on($theme->getDirName());
    }

    /**
     * Save the object to the theme.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = null)
    {
        try {
            parent::save($options);
        }
        catch (Exception $ex) {
            $this->throwHalcyonSaveException($ex);
        }
    }

    /**
     * Returns the CMS theme this object belongs to.
     * @return \Cms\Classes\Theme
     */
    public function getThemeAttribute()
    {
        if ($this->themeCache !== null) {
            return $this->themeCache;
        }

        $themeName = $this->getDatasourceName()
            ?: static::getDatasourceResolver()->getDefaultDatasource();

        return $this->themeCache = Theme::load($themeName);
    }

    /**
     * Returns the full path to the template file corresponding to this object.
     * @param  string  $fileName
     * @return string
     */
    public function getFilePath($fileName = null)
    {
        if ($fileName === null) {
            $fileName = $this->fileName;
        }

        $directory = $this->theme->getPath() . '/' . $this->getObjectTypeDirName() . '/';
        $filePath = $directory . $fileName;

        // Limit paths to those under the corresponding theme directory
        if (!PathResolver::within($filePath, $directory)) {
            return false;
        }

        return PathResolver::resolve($filePath);
    }

    /**
     * Returns the file name.
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * Returns the file name without the extension.
     * @return string
     */
    public function getBaseFileName()
    {
        $pos = strrpos($this->fileName, '.');
        if ($pos === false) {
            return $this->fileName;
        }

        return substr($this->fileName, 0, $pos);
    }

    /**
     * Helper for {{ page.id }} or {{ layout.id }} twig vars
     * Returns a unique string for this object.
     * @return string
     */
    public function getId()
    {
        return str_replace('/', '-', $this->getBaseFileName());
    }

    /**
     * Returns the file content.
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns the Twig content string.
     * @return string
     */
    public function getTwigContent()
    {
        return $this->content;
    }

    /**
     * Returns the key used by the Twig cache.
     * @return string
     */
    public function getTwigCacheKey()
    {
        $key = $this->getFilePath();

        if ($event = $this->fireEvent('cmsObject.getTwigCacheKey', compact('key'), true)) {
            $key = $event;
        }

        return $key;
    }

    //
    // Internals
    //

    /**
     * Converts an exception type thrown by Halcyon to a native CMS exception.
     * @param Exception $ex
     */
    protected function throwHalcyonSaveException(Exception $ex)
    {
        if ($ex instanceof \Winter\Storm\Halcyon\Exception\MissingFileNameException) {
            throw new ValidationException([
                'fileName' => Lang::get('cms::lang.cms_object.file_name_required')
            ]);
        }
        elseif ($ex instanceof \Winter\Storm\Halcyon\Exception\InvalidExtensionException) {
            throw new ValidationException(['fileName' =>
                Lang::get('cms::lang.cms_object.invalid_file_extension', [
                    'allowed' => implode(', ', $ex->getAllowedExtensions()),
                    'invalid' => $ex->getInvalidExtension()
                ])
            ]);
        }
        elseif ($ex instanceof \Winter\Storm\Halcyon\Exception\InvalidFileNameException) {
            throw new ValidationException([
               'fileName' => Lang::get('cms::lang.cms_object.invalid_file', ['name'=>$ex->getInvalidFileName()])
            ]);
        }
        elseif ($ex instanceof \Winter\Storm\Halcyon\Exception\FileExistsException) {
            throw new ApplicationException(
                Lang::get('cms::lang.cms_object.file_already_exists', ['name' => $ex->getInvalidPath()])
            );
        }
        elseif ($ex instanceof \Winter\Storm\Halcyon\Exception\CreateDirectoryException) {
            throw new ApplicationException(
                Lang::get('cms::lang.cms_object.error_creating_directory', ['name' => $ex->getInvalidPath()])
            );
        }
        elseif ($ex instanceof \Winter\Storm\Halcyon\Exception\CreateFileException) {
            throw new ApplicationException(
                Lang::get('cms::lang.cms_object.error_saving', ['name' => $ex->getInvalidPath()])
            );
        }
        else {
            throw $ex;
        }
    }
}
