<?php

namespace Smartling\ACF;

use Psr\Log\LoggerInterface;
use Smartling\ContentTypeAcfOption;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class AcfAutoSetup
 * @package Smartling\ACF
 */
class AcfAutoSetup
{
    const LOG_PREFIX = 'ACF Localization :: ';

    /**
     * @var ContainerBuilder
     */
    private $di;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->getDi()->get('logger');
    }

    private $definitions = [];

    private $rules = [
        'skip'      => [],
        'copy'      => [],
        'localize'  => [],
        'translate' => [],
    ];

    private $filters = [];

    /**
     * @return SiteHelper
     */
    public function getSiteHelper()
    {
        return $this->getEntityHelper()->getSiteHelper();
    }

    public function getEntityHelper()
    {
        return $this->getDi()->get('entity.helper');
    }

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

    public function collectDefinitions()
    {

        $defs = [];

        $this->getLogger()->debug(vsprintf('%sLooking for ACF definitions in the database', [self::LOG_PREFIX]));
        $blogs = $this->getSiteHelper()->listBlogs();
        $profiles = $this->getEntityHelper()->getSettingsManager()->getEntities();
        $blogsToSearch = [];

        foreach ($profiles as $profile) {
            /**
             * @var ConfigurationProfileEntity $profile
             */
            if (($profile instanceof ConfigurationProfileEntity) && 1 === (int)$profile->getIsActive() &&
                in_array($profile->getOriginalBlogId()->getBlogId(), $blogs)
            ) {
                $blogsToSearch[] = $profile->getOriginalBlogId()->getBlogId();
            }
        }

        foreach ($blogsToSearch as $blog) {

            try {
                $this->getLogger()->debug(vsprintf('%sLooking for profiles for blog %s', [self::LOG_PREFIX, $blog]));
                $applicableProfiles = $this->getEntityHelper()->getSettingsManager()->findEntityByMainLocale($blog);
                if (0 === count($applicableProfiles)) {
                    $this->getLogger()
                        ->debug(vsprintf('%sNo suitable profile found for this blog %s', [self::LOG_PREFIX, $blog]));
                } else {

                    $groups = $this->getGroups($blog);

                    if (0 < count($groups)) {
                        foreach ($groups as $groupKey => $group) {

                            $defs[$groupKey] = [
                                'global_type' => 'group',
                                'active'      => 1,
                            ];

                            $fields = $this->getFieldsByGroup($blog, [$groupKey => $group]);

                            if (0 < count($fields) && false !== $fields) {
                                foreach ($fields as $fieldKey => $field) {
                                    $defs[$fieldKey] = [
                                        'global_type' => 'field',
                                        'type'        => $field['type'],
                                        'name'        => $field['name'],
                                        'parent'      => $field['parent'],
                                    ];
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->getLogger()
                    ->warning(vsprintf('%sAn error occurred while generating filters from database', [self::LOG_PREFIX]));
            }
        }

        return $defs;
    }

    public function __construct(ContainerBuilder $di)
    {
        $this->setDi($di);
    }

    protected function getGroups($blogId)
    {
        $dbGroups = [];
        $needChange = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;
        try {
            if ($needChange) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }
            $dbGroups = $this->rawReadGroups();

        } catch (\Exception $e) {
            $this->getLogger()->warning(
                vsprintf('%sError occurred while reading ACF data from blog %s. Message: %s',
                         [self::LOG_PREFIX, $blogId, $e->getMessage()]
                )
            );
        } finally {
            if ($needChange) {
                $this->getSiteHelper()->restoreBlogId();
            }
        }

        return $dbGroups;
    }

    /**
     * Reads the list of groups from database
     * @return array
     */
    private function rawReadGroups()
    {
        $args = [
            'posts_per_page'   => 100000,
            'post_type'        => 'acf-field-group',
            'orderby'          => 'menu_order title',
            'order'            => 'ASC',
            'suppress_filters' => false,
            'post_status'      => ['publish'],
        ];
        $posts = get_posts($args);
        $groups = [];
        foreach ($posts as $post) {
            $groups[$post->post_name] = [
                'title'   => $post->post_title,
                'post_id' => $post->ID,
            ];
        }

        return $groups;
    }

    private function rawReadFields($parentId, $parentKey)
    {
        $args = [
            'posts_per_page'   => 100000,
            'post_type'        => 'acf-field',
            'orderby'          => 'menu_order title',
            'order'            => 'ASC',
            'suppress_filters' => false,
            'post_status'      => ['publish'],
            'post_parent'      => $parentId,
        ];
        $posts = get_posts($args);
        $fields = [];
        foreach ($posts as $post) {
            $configuration = unserialize($post->post_content);
            $fields[$post->post_name] = [
                'parent' => $parentKey,
                'name'   => $post->post_excerpt,
                'type'   => $configuration['type'],
            ];
            $subFields = $this->rawReadFields($post->ID, $post->post_name);
            if (0 < count($subFields)) {
                $fields = array_merge($fields, $subFields);
            }
        }

        return $fields;
    }

    protected function getFieldsByGroup($blogId, $group)
    {
        $dbFields = [];
        $needChange = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;
        try {
            if ($needChange) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }
            $keys = array_keys($group);
            $key = reset($keys);
            $_group = reset($group);
            $id = $_group['post_id'];

            $dbFields = $this->rawReadFields($id, $key);

        } catch (\Exception $e) {
            $this->getLogger()->warning(
                vsprintf('%sError occurred while reading ACF data from blog %s. Message: %s',
                         [self::LOG_PREFIX, $blogId, $e->getMessage()]
                )
            );
        } finally {
            if ($needChange) {
                $this->getSiteHelper()->restoreBlogId();
            }
        }

        return $dbFields;
    }

    private function getLocaldefinitions()
    {
        $acf = (array)$this->getAcf();

        $defs = [];

        if (array_key_exists('local', $acf)) {
            if ($acf['local'] instanceof \acf_local) {
                /**
                 * @var \acf_local $local
                 */
                $local = $acf['local'];
                $groups = $local->groups;

                if (is_array($groups) && 0 < count($groups)) {
                    foreach ($groups as $group) {
                        $defs[$group['key']] = [
                            'global_type' => 'group',
                            'active'      => $group['active'],
                        ];
                    }
                }

                $fields = $local->fields;

                if (is_array($fields) && 0 < count($fields)) {
                    foreach ($fields as $field) {
                        $defs[$field['key']] = [
                            'global_type' => 'field',
                            'type'        => $field['type'],
                            'name'        => $field['name'],
                            'parent'      => $field['parent'],
                        ];
                    }
                }
            }
        }

        return $defs;
    }

    /**
     * @param array $localDefinitions
     * @param array $dbDefinitions
     *
     * @return bool
     */
    private function verifyDefinitions(array $localDefinitions, array $dbDefinitions)
    {
        foreach ($dbDefinitions as $key => $definition) {
            if (!array_key_exists($key, $localDefinitions)) {
                return false;
            } else {
                switch ($definition['global_type']) {
                    case 'field':
                        $local = &$localDefinitions[$key];
                        $dbdef = &$definition;
                        if ($local['type'] !== $dbdef['type'] || $local['name'] !== $dbdef['name'] ||
                            $local['parent'] !== $dbdef['parent']
                        ) {
                            // ACF Option Pages has internal issue in definition, so skip it:
                            if ('group_572b269b668a4' === $local['parent']) {
                                continue;
                            }

                            return false;
                        }
                        break;
                    case 'group':
                    default:
                }
            }
        }

        return true;
    }

    public function run()
    {
        if (true === $this->checkOptionPages()) {
            ContentTypeAcfOption::register($this->getDi());
            add_filter(
                'smartling_register_field_filter',
                function (array $defs) {
                    return array_merge(
                        $defs,
                        [
                            [
                                'pattern' => 'menu_slug$',
                                'action'  => 'copy',
                            ],
                        ]
                    );
                }
            );
        }

        if (true === $this->checkAcfTypes()) {
            $dbDefinitions = $this->collectDefinitions();
            $localDefinitions = $this->getLocaldefinitions();
            if (false === $this->verifyDefinitions($localDefinitions, $dbDefinitions)) {
                $url = admin_url('edit.php?post_type=acf-field-group&page=acf-settings-tools');
                $msg = [
                    'ACF Configuration has been changed.',
                    'Please update groups and fields definitions for all sites (As PHP generated code).',
                    vsprintf('Use <strong><a href="%s">this</a></strong> page to generate export code and add it to your theme or extra plugin.', [$url]),
                ];
                DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
            }
            $definitions = array_merge($localDefinitions, $dbDefinitions);
            $this->definitions = $definitions;
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
                return 'taxonomy';
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
                case 'flexible_content':
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
                case 'repeater':
                case 'message':
                case 'tab':
                    break;
                default:
                    $this->getDi()->get('logger')->debug(vsprintf('Got unknown type: %s', [$definition['type']]));
            }
        }
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