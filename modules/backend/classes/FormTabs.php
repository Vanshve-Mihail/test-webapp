<?php namespace Backend\Classes;

use IteratorAggregate;
use ArrayIterator;
use ArrayAccess;
use Traversable;

/**
 * Form Tabs definition
 * A translation of the form field tab configuration
 *
 * @package winter\wn-backend-module
 * @author Alexey Bobkov, Samuel Georges
 */
class FormTabs implements IteratorAggregate, ArrayAccess
{
    const SECTION_OUTSIDE = 'outside';
    const SECTION_PRIMARY = 'primary';
    const SECTION_SECONDARY = 'secondary';

    /**
     * @var string Specifies the form section these tabs belong to.
     */
    public $section = 'outside';

    /**
     * @var array Collection of panes fields to these tabs.
     */
    public $fields = [];

    /**
     * @var array Names of tabs to lazy load.
     */
    public $lazy = [];

    /**
     * @var string Default tab label to use when none is specified.
     */
    public $defaultTab = 'backend::lang.form.undefined_tab';

    /**
     * @var array List of icons for their corresponding tabs.
     */
    public $icons = [];

    /**
     * @var bool Should these tabs stretch to the bottom of the page layout.
     */
    public $stretch;

    /**
     * @var boolean If set to TRUE, fields will not be displayed in tabs.
     */
    public $suppressTabs = false;

    /**
     * @var string Specifies a CSS class to attach to the tab container.
     */
    public $cssClass;

    /**
     * @var array Specifies a CSS class to an individual tab pane.
     */
    public $paneCssClass;

    /**
     * @var bool Each tab gets url fragment to be linkable.
     */
    public $linkable = true;

    /**
     * Constructor.
     * Specifies a tabs rendering section. Supported sections are:
     * - outside - stores a section of "tabless" fields.
     * - primary - tabs section for primary fields.
     * - secondary - tabs section for secondary fields.
     * @param string $section Specifies a section as described above.
     * @param array $config A list of render mode specific config.
     */
    public function __construct($section, $config = [])
    {
        $this->section = strtolower($section) ?: $this->section;
        $this->evalConfig($config);

        if ($this->section == self::SECTION_OUTSIDE) {
            $this->suppressTabs = true;
        }
    }

    /**
     * Process options and apply them to this object.
     */
    protected function evalConfig(array $config): void
    {
        if (array_key_exists('defaultTab', $config)) {
            $this->defaultTab = $config['defaultTab'];
        }

        if (array_key_exists('icons', $config)) {
            $this->icons = $config['icons'];
        }

        if (array_key_exists('stretch', $config)) {
            $this->stretch = $config['stretch'];
        }

        if (array_key_exists('suppressTabs', $config)) {
            $this->suppressTabs = $config['suppressTabs'];
        }

        if (array_key_exists('cssClass', $config)) {
            $this->cssClass = $config['cssClass'];
        }

        if (array_key_exists('paneCssClass', $config)) {
            $this->paneCssClass = $config['paneCssClass'];
        }

        if (array_key_exists('linkable', $config)) {
            $this->linkable = (bool) $config['linkable'];
        }

        if (array_key_exists('lazy', $config)) {
            $this->lazy = $config['lazy'];
        }
    }

    /**
     * Add a field to the collection of tabs.
     * @param string    $name
     * @param FormField $field
     * @param string    $tab
     */
    public function addField($name, FormField $field, $tab = null)
    {
        if (!$tab) {
            $tab = $this->defaultTab;
        }

        $this->fields[$tab][$name] = $field;
    }

    /**
     * Remove a field from all tabs by name.
     * @param string    $name
     * @return boolean
     */
    public function removeField($name)
    {
        foreach ($this->fields as $tab => $fields) {
            foreach ($fields as $fieldName => $field) {
                if ($fieldName == $name) {
                    unset($this->fields[$tab][$fieldName]);

                    /*
                     * Remove empty tabs from collection
                     */
                    if (!count($this->fields[$tab])) {
                        unset($this->fields[$tab]);
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns true if any fields have been registered for these tabs
     * @return boolean
     */
    public function hasFields()
    {
        return count($this->fields) > 0;
    }

    /**
     * Returns an array of the registered fields, including tabs.
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Returns an array of the registered fields, without tabs.
     * @return array
     */
    public function getAllFields()
    {
        $tablessFields = [];

        foreach ($this->getFields() as $tab) {
            $tablessFields += $tab;
        }

        return $tablessFields;
    }

    /**
     * Returns an icon for the tab based on the tab's name.
     * @param string $name
     * @return string
     */
    public function getIcon($name)
    {
        if (!empty($this->icons[$name])) {
            return $this->icons[$name];
        }
    }

    /**
     * Returns a tab pane CSS class.
     * @param string $index
     * @param string $label
     * @return string
     */
    public function getPaneCssClass($index = null, $label = null)
    {
        if (is_string($this->paneCssClass)) {
            return $this->paneCssClass;
        }

        if ($index !== null && isset($this->paneCssClass[$index])) {
            return $this->paneCssClass[$index];
        }

        if ($label !== null && isset($this->paneCssClass[$label])) {
            return $this->paneCssClass[$label];
        }
    }

    /**
     * Get an iterator for the items.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(
            $this->suppressTabs
                ? $this->getAllFields()
                : $this->getFields()
        );
    }

    /**
     * ArrayAccess implementation
     */
    public function offsetSet($offset, $value): void
    {
        $this->fields[$offset] = $value;
    }

    /**
     * ArrayAccess implementation
     */
    public function offsetExists($offset): bool
    {
        return isset($this->fields[$offset]);
    }

    /**
     * ArrayAccess implementation
     */
    public function offsetUnset($offset): void
    {
        unset($this->fields[$offset]);
    }

    /**
     * ArrayAccess implementation
     */
    public function offsetGet($offset): mixed
    {
        return $this->fields[$offset] ?? null;
    }
}
