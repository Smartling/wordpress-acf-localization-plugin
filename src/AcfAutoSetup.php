<?php

namespace Smartling\ACF;

use Smartling\Bootstrap;
use Smartling\ContentTypeAcfOption;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class AcfAutoSetup
 * @package Smartling\ACF
 */
class AcfAutoSetup
{
    /**
     * @var ContainerBuilder
     */
    private $di;

    private $definitions = [];

    private $rules = [
        'skip'      => [],
        'copy'      => [],
        'localize'  => [],
        'translate' => [],
    ];

    private $filters = [];

    public function filterSetup(array $filters)
    {
        return array_merge($filters, $this->filters);
    }

    /**
     * @return \acf
     */
    private function getAcf()
    {
        global $acf;

        return $acf;
    }

    /**
     * @return ContainerBuilder
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param ContainerBuilder $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    public function __construct(ContainerBuilder $di)
    {
        $this->setDi($di);
    }

    public function run()
    {
        if (true === $this->checkOptionPages()) {
            ContentTypeAcfOption::register($this->getDi());
        }

        if (true === $this->checkAcfTypes()) {
            $acf = $this->getAcf();

            foreach ($acf->local->groups as $group) {
                $this->addGroupDefinition($group);
            }

            foreach ($acf->local->fields as $field) {
//                Bootstrap::DebugPrint($field);
                $this->addFieldDefinition($field);
            }


            $this->sortFields();

            $this->prepareFilters();

            add_filter('smartling_register_field_filter', [$this, 'filterSetup'], 1);
        }
    }

    private function prepareFilters()
    {
        $rules = [];


        if (0 < count($this->rules['copy'])) {
            foreach ($this->rules['copy'] as $key) {
                $rules[] = [
                    'pattern' => vsprintf('^%s$', [$this->buildFullFieldName($key)]),
                    'action'  => 'copy',
                ];

            }
        }

        if (0 < count($this->rules['skip'])) {
            foreach ($this->rules['skip'] as $key) {
                $rules[] = [
                    'pattern' => vsprintf('^%s$', [$this->buildFullFieldName($key)]),
                    'action'  => 'skip',
                ];

            }
        }

        if (0 < count($this->rules['localize'])) {
            foreach ($this->rules['localize'] as $key) {
                $rules[] = [
                    'pattern'       => vsprintf('^%s$', [$this->buildFullFieldName($key)]),
                    'action'        => 'localize',
                    'value'         => 'reference',
                    'serialization' => $this->getSerializationTypeByKey($key),
                    'type'          => $this->getReferencedTypeByKey($key),
                ];

            }
        }

        $this->filters = $rules;
    }

    private function getFieldTypeByKey($key)
    {
        $def = &$this->definitions;

        return array_key_exists($key, $def) && array_key_exists('type', $def[$key]) ? $def[$key]['type'] : false;
    }

    private function getSerializationTypeByKey($key)
    {
        $type = $this->getFieldTypeByKey($key);

        switch ($type) {
            case 'image':
            case 'file':
            case 'post_object':
            case 'page_link':
                return 'none';
                break;
            case 'relationship':
            case 'gallery':
            case 'taxonomy':
                return 'none';
                //return 'array-value';
                break;
            default:
                return 'none';
        }
    }

    private function getReferencedTypeByKey($key)
    {
        $type = $this->getFieldTypeByKey($key);

        switch ($type) {
            case 'image':
            case 'file':
            case 'gallery':
                return 'media';
                break;
            case 'post_object':
            case 'page_link':
            case 'relationship':
                return 'post';
                break;
            case 'taxonomy':
                $t = $this->getAcf()->local->fields[$key];

                return $t['taxonomy'];
                break;
            default:
                return 'none';
        }
    }

    private function buildFullFieldName($fieldId)
    {
        $definition = &$this->definitions[$fieldId];
        $pattern = '|field_[0-9A-F]{12,12}|ius';
        $prefix = '';
        if (array_key_exists('parent', $definition) && preg_match($pattern, $definition['parent'])) {
            $prefix = $this->buildFullFieldName($definition['parent']) . '_\d+_';
        }

        return $prefix . $definition['name'];
    }

    private function sortFields()
    {
        foreach ($this->definitions as $id => $definition) {
            if ('group' === $definition['global_type']) {
                continue;
            }
            //Bootstrap::DebugPrint($definition, true);
            switch ($definition['type']) {
                case 'text':
                case 'textarea':
                case 'wysiwyg':
                    $this->rules['translate'][] = $id;
                    break;
                case 'number':
                case 'email':
                case 'url':
                case 'password':
                case 'oembed':
                case 'select':
                case 'checkbox':
                case 'radio':
                case 'choice':
                case 'true_false':
                case 'date_picker':
                case 'date_time_picker':
                case 'time_picker':
                case 'color_picker':
                case 'google_map':
                    $this->rules['copy'][] = $id;
                    break;
                case 'user':
                    $this->rules['skip'][] = $id;
                    break;
                case 'image':
                case 'file':
                case 'post_object':
                case 'page_link':
                case 'relationship':
                case 'gallery':
                case 'taxonomy': // look into taxonomy
                    $this->rules['localize'][] = $id;
                    break;
                default:
                    $this->getDi()->get('logger')->debug(vsprintf('Got unknown type: %s', [$definition['type']]));
            }
        }
    }

    private function addGroupDefinition(array $rawGroupDefinition)
    {
        $this->definitions[$rawGroupDefinition['key']] = [
            'global_type' => 'group',
            'active'      => $rawGroupDefinition['active'],
        ];
    }

    private function addFieldDefinition(array $rawFieldDefinition)
    {
        $this->definitions[$rawFieldDefinition['key']] = [
            'global_type' => 'field',
            'type'        => $rawFieldDefinition['type'],
            'name'        => $rawFieldDefinition['name'],
            'parent'      => $rawFieldDefinition['parent'],
        ];
    }

    private function getPostTypes()
    {
        return array_keys(get_post_types());
    }

    private function checkAcfTypes()
    {
        return in_array('acf-field', $this->getPostTypes(), true) &&
               in_array('acf-field-group', $this->getPostTypes(), true);
    }

    /**
     * Checks if acf_option_page exists
     * @return bool
     */
    private function checkOptionPages()
    {
        return in_array('acf_option_page', $this->getPostTypes(), true);
    }

    /**
     * @param ContainerBuilder $di
     */
    public static function register(ContainerBuilder $di)
    {
        $obj = new static($di);

        add_action('admin_init', function () use ($obj) {
            $obj->run();
        });
    }
}