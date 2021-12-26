<?php
/**
 * Plugin Name: Source Storage Using
 * Description: 使用AHdark提供的Source Storage静态加速服务和SDN境外资源加速服务
 * Author: AHdark
 * Author URI:https://ahdark.com
 * Version: 1.0.0
 * Network: True
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

if (!class_exists('SOURCE_STORAGE_USING')) {
    class SOURCE_STORAGE_USING
    {
        private $page_url;

        public function __construct()
        {
            $this->page_url = network_admin_url(is_multisite() ? 'settings.php?page=source-storage-using' : 'options-general.php?page=source-storage-using');
        }

        public function init()
        {
            if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
                /**
                 * 插件列表项目中增加设置项
                 */
                add_filter(sprintf('%splugin_action_links_%s', is_multisite() ? 'network_admin_' : '', plugin_basename(__FILE__)), function ($links) {
                    return array_merge(
                        [sprintf('<a href="%s">%s</a>', $this->page_url, '设置')],
                        $links
                    );
                });

                /**
                 * 初始化设置项
                 */
                update_option("source_admin", get_option('source_admin') ?: '2');
                update_option("sdn_gravatar", get_option('sdn_gravatar') ?: '1');


                /**
                 * 禁用插件时删除配置
                 */
                register_deactivation_hook(__FILE__, function () {
                    delete_option("source_admin");
                    delete_option("sdn_gravatar");
                });


                /**
                 * 菜单注册
                 */
                add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', function () {
                    add_submenu_page(
                        is_multisite() ? 'settings.php' : 'options-general.php',
                        'Source Storage Using',
                        'Source Storage',
                        is_multisite() ? 'manage_network_options' : 'manage_options',
                        'source-storage-using',
                        [$this, 'options_page_html']
                    );
                });
            }

            /**
             * 将WordPress核心所依赖的静态文件访问链接替换为公共资源节点
             */
            if (
                get_option('source_admin') != 2 &&
                !stristr($GLOBALS['wp_version'], 'alpha') &&
                !stristr($GLOBALS['wp_version'], 'beta') &&
                !stristr($GLOBALS['wp_version'], 'RC') &&
                !isset($GLOBALS['lp_version'])
            ) {
                $this->page_str_replace('preg_replace', [
                    '~' . home_url('/') . '(wp-admin|wp-includes)/(css|js)/~',
                    sprintf('https://source.ahdark.com/wordpress/%s/$1/$2/', $GLOBALS['wp_version'])
                ], get_option('source_admin'));
            }

            if (is_admin() || wp_doing_cron()) {
                add_action('admin_init', function () {
                    /**
                     * source_admin用以标记用户是否启用管理后台加速功能
                     */
                    register_setting('wpsource', 'source_admin');

                    /**
                     * sdn_gravatar用以标记用户是否启用G家头像加速功能
                     */
                    register_setting('wpsource', 'sdn_gravatar');

                    add_settings_section(
                        'wpsource_section_main',
                        '管理',
                        '',
                        'wpsource'
                    );

                    add_settings_field(
                        'wpsource_field_select_source_admin',
                        'WordPress核心加速',
                        [$this, 'field_source_admin_cb'],
                        'wpsource',
                        'wpsource_section_main'
                    );

                    add_settings_field(
                        'wpsource_field_select_sdn_gravatar',
                        'Gravatar头像加速',
                        [$this, 'field_sdn_gravatar_cb'],
                        'wpsource',
                        'wpsource_section_main'
                    );
                });

            }

            if (get_option('sdn_gravatar') == 1) {
                /**
                 * 替换使用sdn.ahdark.com镜像源
                 */
                function get_avatar_from_sdn($avatar)
                {
                    return str_replace(
                        [
                            'www.gravatar.com',
                            '0.gravatar.com',
                            '1.gravatar.com',
                            '2.gravatar.com',
                            'secure.gravatar.com',
                            'cn.gravatar.com',
                            'gravatar.com',
                            "sdn.geekzu.com",
                            "cravatar.cn"
                        ],
                        'sdn.ahdark.com',
                        $avatar
                    );
                }

                add_filter('get_avatar', 'get_avatar_from_sdn');
                add_filter('um_user_avatar_url_filter', 'get_avatar_from_sdn', 1);
                add_filter('bp_gravatar_url', 'get_avatar_from_sdn', 1);
                add_filter('get_avatar_url', 'get_avatar_from_sdn', 1);
            }
        }

        public function field_source_admin_cb()
        {
            $this->field_cb('source_admin', '将WordPress静态文件使用 <code>source.ahdark.com</code>分发, 这将大大加快您WordPress的速度');
        }

        public function field_sdn_gravatar_cb()
        {
            $this->field_cb('sdn_gravatar', '使用 <code>sdn.ahdark.com</code> 确保您的Gravatar头像在中国大陆正常访问，并加快您Gravatar头像的速度');
        }

        public function options_page_html()
        {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                update_option("source_admin", sanitize_text_field($_POST['source_admin']));
                update_option("sdn_gravatar", sanitize_text_field($_POST['sdn_gravatar']));

                echo '<div class="notice notice-success settings-error is-dismissible"><p><strong>已保存</strong></p></div>';
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            settings_errors('wpsource_messages');
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <form action="<?php echo $this->page_url; ?>" method="post">
                    <?php
                    settings_fields('wpsource');
                    do_settings_sections('wpsource');
                    submit_button('保存');
                    ?>
                </form>
            </div>
            <p>
                关于此项目更多内容，请去 <a href="https://ahdark.com/source" target="_blank" rel="noopener">ahdark.com/source/</a>. 此插件由AHdark制作，由<a href="https://xcao.top" target="_blank" rel="noopener">小草</a>汉化
            </p>
            <?php
        }

        private function field_cb($option_name, $description)
        {
            $option_value = get_option($option_name);
            ?>
            <label>
                <input type="radio" value="1"
                       name="<?php echo $option_name; ?>" <?php checked($option_value, '1'); ?>>启用
            </label>
            <label>
                <input type="radio" value="2"
                       name="<?php echo $option_name; ?>" <?php checked($option_value, '2'); ?>>禁用
            </label>
            <p class="description">
                <?php echo $description; ?>
            </p>
            <?php
        }

        /**
         * @param $replace_func string 要调用的字符串关键字替换函数
         * @param $param array 传递给字符串替换函数的参数
         * @param $level int 替换级别：1.全局替换 3.前台替换 4.后台替换
         */
        private function page_str_replace(string $replace_func, array $param, int $level)
        {
            if ($level == 3 && is_admin()) {
                return;
            } else if ($level == 4 && !is_admin()) {
                return;
            }

            add_action('init', function () use ($replace_func, $param) {
                ob_start(function ($buffer) use ($replace_func, $param) {
                    $param[] = $buffer;

                    return call_user_func_array($replace_func, $param);
                });
            });
        }
    }

    (new SOURCE_STORAGE_USING())->init();
}