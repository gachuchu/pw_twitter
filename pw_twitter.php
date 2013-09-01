<?php /*@charset "utf-8"*/
/*********************************************************************
 Plugin Name:   PW_Twitter
 Plugin URI:    http://syncroot.com/
 Description:   投稿時につぶやいたり、ページにつぶやきやアイコンを表示したり
 Author:        gachuchu
 Version:       1.0.0
 Author URI:    http://syncroot.com/
 *********************************************************************/

/*********************************************************************
 Copyright 2010 gachuchu  (email : syncroot.com@gmail.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *********************************************************************/
define("IS_TWITTEROAUTH", false);

require_once(WP_PLUGIN_DIR . "/libpw/libpw.php");
if(IS_TWITTEROAUTH){
    require_once(dirname(__FILE__) . '/twitteroauth/twitteroauth.php');
}else{
    require_once(dirname(__FILE__) . '/tmhOAuth/tmhOAuth.php');
}

if(!class_exists('PW_Twitter')){
    class PwOAuth {
        private $api;
        public function __construct($consumer_key, $consumer_secret, $access_token, $access_token_secret) {
            if(IS_TWITTEROAUTH){
                $this->api = new TwitterOAuth($consumer_key,
                                              $consumer_secret,
                                              $access_token,
                                              $access_token_secret
                                              );
            }else{
                $this->api = new tmhOAuth(array(
                    'consumer_key' => $consumer_key,
                    'consumer_secret' => $consumer_secret,
                    'token' => $access_token,
                    'secret' => $access_token_secret,
                    ));
            }
        }

        public function request($method, $url, $params = array(), $useauth = true, $multipart = false, $headers = array()) {
            if(IS_TWITTEROAUTH){
                return $this->api->oAuthRequest($url, $method, $params);
            }else{
                $code = $this->api->request($method, $url, $params, $useauth, $multipart, $headers);
                if($code == 200){
                    return $this->api->response['response'];
                }else{
                    return null;
                }
            }
        }
    }
    
    /**
     *********************************************************************
     * 本体
     *********************************************************************/
    class PW_Twitter extends libpw_Plugin_Substance {
        //---------------------------------------------------------------------
        // end point
        const END_POINT = 'https://api.twitter.com/1.1/';
        
        //---------------------------------------------------------------------
        const UNIQUE_KEY = 'PW_Twitter';
        const CLASS_NAME = 'PW_Twitter';

        //---------------------------------------------------------------------
        const OPT_USER_NAME                     = 'user_name';
        const OPT_USER_ICON_LIFETIME            = 'user_icon_lifetime';
        const OPT_USER_ICON_PREFIX              = 'user_icon_prefix';
        const USER_ICON_CACHE_PATH_BASE         = 'user_icon_cache_path_';
        const OPT_USER_ICON_CACHE_PATH_MINI     = 'user_icon_cache_path_mini';
        const OPT_USER_ICON_CACHE_PATH_NORMAL   = 'user_icon_cache_path_normal';
        const OPT_USER_ICON_CACHE_PATH_BIGGER   = 'user_icon_cache_path_bigger';
        const OPT_USER_ICON_CACHE_PATH_ORIGINAL = 'user_icon_cache_path_original';
        const USER_ICON_CACHE_TIME_BASE         = 'user_icon_cache_time_';
        const OPT_USER_ICON_CACHE_TIME_MINI     = 'user_icon_cache_time_mini';
        const OPT_USER_ICON_CACHE_TIME_NORMAL   = 'user_icon_cache_time_normal';
        const OPT_USER_ICON_CACHE_TIME_BIGGER   = 'user_icon_cache_time_bigger';
        const OPT_USER_ICON_CACHE_TIME_ORIGINAL = 'user_icon_cache_time_original';
        const OPT_CONSUMER_KEY                  = 'api_consumer_key';
        const OPT_CONSUMER_KEY_SECRET           = 'api_consumer_key_secret';
        const OPT_ACCESS_TOKEN                  = 'api_access_token';
        const OPT_ACCESS_TOKEN_SECRET           = 'api_access_token_secret';
        const OPT_POST_TWEET_MESSAGE            = 'post_tweet_message';

        //---------------------------------------------------------------------
        const POST_RULE_ON_PUBLISH  = 0;
        const POST_RULE_FORCE_TWEET = 1;
        const POST_RULE_NOT_TWEET   = 2;

        //---------------------------------------------------------------------
        private $opt;
        private $api;
        private $custom;

        /**
         *====================================================================
         * 初期化
         *===================================================================*/
        public function init() {
            // オプション設定
            $this->opt = new libpw_Plugin_DataStore($this->unique . '_OPT',
                                                    array(
                                                        self::OPT_USER_NAME                     => '',
                                                        self::OPT_USER_ICON_LIFETIME            => '3600',
                                                        self::OPT_USER_ICON_PREFIX              => '',
                                                        self::OPT_USER_ICON_CACHE_PATH_MINI     => 'dummy/user_icon_min.png',
                                                        self::OPT_USER_ICON_CACHE_PATH_NORMAL   => 'dummy/user_icon_normal.png',
                                                        self::OPT_USER_ICON_CACHE_PATH_BIGGER   => 'dummy/user_icon_bigger.png',
                                                        self::OPT_USER_ICON_CACHE_PATH_ORIGINAL => 'dummy/user_icon_original.png',
                                                        self::OPT_USER_ICON_CACHE_TIME_MINI     => '',
                                                        self::OPT_USER_ICON_CACHE_TIME_NORMAL   => '',
                                                        self::OPT_USER_ICON_CACHE_TIME_BIGGER   => '',
                                                        self::OPT_USER_ICON_CACHE_TIME_ORIGINAL => '',
                                                        self::OPT_CONSUMER_KEY                  => '',
                                                        self::OPT_CONSUMER_KEY_SECRET           => '',
                                                        self::OPT_ACCESS_TOKEN                  => '',
                                                        self::OPT_ACCESS_TOKEN_SECRET           => '',
                                                        self::OPT_POST_TWEET_MESSAGE            => 'ブログ更新しました %POST_TITLE%',
                                                        )
                                                    );
            // api作成
            $this->opt->load();
            $this->api = new PwOAuth($this->opt->get(self::OPT_CONSUMER_KEY),
                                     $this->opt->get(self::OPT_CONSUMER_KEY_SECRET),
                                     $this->opt->get(self::OPT_ACCESS_TOKEN),
                                     $this->opt->get(self::OPT_ACCESS_TOKEN_SECRET)
                                     );
            $this->opt->clear();

            // 管理メニュー
            $this->addMenu($this->unique . 'の設定ページ',
                           $this->unique);

            // カスタムボックス
            $this->custom = array(
                'pwtw_custom_rule' => array(
                    'name'              =>      'pwtw_custom_rule',
                    'default'           =>      self::POST_RULE_ON_PUBLISH,
                    'title'             =>      'ツイトールール',
                    'description'       =>      ' <span style="font-size:10px;">今回の投稿に適用する投稿時ツイートのルール設定</span>',
                    ),
                'pwtw_custom_add1' => array(
                    'name'              =>      'pwtw_custom_add1',
                    'default'           =>      '',
                    'title'             =>      'カスタム追加1(%ADD1%)',
                    'description'       =>      ' <span style="font-size:10px;">投稿時ツイートに引き渡せるカスタム入力フィールド1</span>',
                    ),
                'pwtw_custom_add2' => array(
                    'name'              =>      'pwtw_custom_add2',
                    'default'           =>      '',
                    'title'             =>      'カスタム追加2(%ADD2&)',
                    'description'       =>      ' <span style="font-size:10px;">投稿時ツイートに引き渡せるカスタム入力フィールド2</span>',
                    ),
                );

            // 各種処理
            add_action('transition_post_status',        array(&$this, 'execute_publish_post'), 10, 3);
            add_action('admin_menu',                    array(&$this, 'execute_admin_menu'));
            add_action('save_post',                     array(&$this, 'execute_save_post'));
            add_action('wp_print_scripts',              array(&$this, 'execute_wp_print_scripts'));
        }

        /**
         *====================================================================
         * publish時につぶやく
         *===================================================================*/
        public function execute_publish_post($new_status, $old_status, $post) {
            $do_tweet   = false;
            $tweet_rule = (isset($_POST['pwtw_custom_rule_value']) ? $_POST['pwtw_custom_rule_value'] : self::POST_RULE_ON_PUBLISH);

            switch($tweet_rule){
            case self::POST_RULE_ON_PUBLISH:
                if($new_status == 'publish' && $old_status != 'publish'){
                    $do_tweet = true;
                }
                break;

            case self::POST_RULE_FORCE_TWEET:
                if($new_status == 'publish'){
                    $do_tweet = true;
                }
                break;

            default:
                break;
            }

            if($do_tweet){
                $add1 = (isset($_POST['pwtw_custom_add1_value']) ? $_POST['pwtw_custom_add1_value'] : '');
                $add2 = (isset($_POST['pwtw_custom_add2_value']) ? $_POST['pwtw_custom_add2_value'] : '');
                $this->opt->load();
                $message = $this->opt->get(self::OPT_POST_TWEET_MESSAGE);
                $this->opt->clear();

                $message = str_replace(array('%SITE_NAME%',
                                             '%POST_TITLE%',
                                             '%POST_AUTHOR%',
                                             '%POST_EXCERPT%',
                                             '%POST_URL%',
                                             '%ADD1%',
                                             '%ADD2%',
                                             '%%%',
                                             ),
                                       array(get_bloginfo('name'),
                                             $post->post_title,
                                             $post->post_author,
                                             $post->post_excerpt,
                                             get_permalink($post->ID),
                                             $add1,
                                             $add2,
                                             '%',
                                             ),
                                       $message
                                       );
                if(!IS_TWITTEROAUTH){
                    $message .= "TM";
                }

                $ret = $this->api->request('POST',
                                           self::END_POINT . 'statuses/update.json',
                                           array('status' => $message),
                                           true,
                                           false
                                           );
            }
        }

        /**
         *====================================================================
         * 投稿時カスタムボックス作成
         *===================================================================*/
        public function execute_admin_menu() {
            add_meta_box('pwtw_custom_box', '投稿時つぶやき追加データ', array(&$this, 'add_custom_box'), 'post', 'normal', 'high');
            add_meta_box('pwtw_custom_box', '投稿時つぶやき追加データ', array(&$this, 'add_custom_box'), 'page', 'normal', 'high');
        }

        public function add_custom_box() {
            global $post;

            $str  = '';
            $str .= "<table>";

            foreach($this->custom as $c){
                $noncename = $c['name'] . '_noncename';
                $nonceval  = wp_create_nonce(plugin_basename(__FILE__));
                $name      = $c['name'] . '_value';
                $val = get_post_meta($post->ID, $name, true);
                if($val === ''){
                    $val = $c['default'];
                }

                $str .= '<tr><th style="padding:6px 3px 5px;vertical-align:top;text-align:right;">' . $c['title'] . '</th><td style="padding-bottom:5px">';
                $str .= "<input type=\"hidden\" name=\"$noncename\" id=\"$noncename\" value=\"$nonceval\" />";

                if($c['name'] === 'pwtw_custom_rule'){
                    // select
                    $str .= "<select name=\"$name\">";
                    $str .= "<option value=\"" . self::POST_RULE_ON_PUBLISH . "\" selected=\"selected\">公開時ツイート</option>";
                    $str .= "<option value=\"" . self::POST_RULE_FORCE_TWEET . "\">強制ツイート</option>";
                    $str .= "<option value=\"" . self::POST_RULE_NOT_TWEET . "\">ツイートしない</option>";
                    $str .= "</select>";
                }else{
                    // text
                    $str .= "<input type=\"text\" name=\"$name\" value=\"$val\" size=\"30\" />";
                }
                //$str .= "<br><label for=\"$name\">" . $c['description'] . "</label>";
                $str .= '</td></tr>';
            }

            $str .= "</table>";

            echo $str;
        }

        /**
         *====================================================================
         * セーブ時の処理
         *===================================================================*/
        public function execute_save_post($post_id) {
            foreach($this->custom as $c){
                // データが先ほど作った編集フォームから適切な認証とともに送られてきたかどうかを確認。
                // save_post は自動セーブなど想定時以外にも起動する場合がある（？）。
                $noncename = $c['name'] . '_noncename';

                if(!isset($_POST[$noncename]) || !wp_verify_nonce($_POST[$noncename], plugin_basename(__FILE__))){
                    return $post_id;
                }
                // 自動保存ルーチンかどうかチェック。そうだった場合はフォームを送信しない（何もしない）
                if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
                    return $post_id;
                }
                // パーミッションチェック
                if('page' == $_POST['post_type']){
                    if(!current_user_can('edit_page', $post_id)){
                        return $post_id;
                    }
                }else{
                    if(!current_user_can('edit_post', $post_id)){
                        return $post_id;
                    }
                }

                // 承認ができたのでデータを探して保存
                $name = $c['name'] . '_value';
                $data = $_POST[$name];

                if(get_post_meta($post_id, $name) == ''){
                    add_post_meta($post_id, $name, $data, true);
                }else if($data != get_post_meta($post_id, $name, true)){
                    update_post_meta($post_id, $name, $data);
                }else if($data == ''){
                    delete_post_meta($post_id, $name, '');
                }
            }
        }

        /**
         *====================================================================
         * スクリプト追加
         *===================================================================*/
        public function execute_wp_print_scripts() {
            if(!is_admin()){
                wp_enqueue_script('jquery',
                                  'http://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js',
                                  array(),
                                  '1.9.0');

                wp_enqueue_script('twitter_widget',
                                  'http://platform.twitter.com/widgets.js');

                wp_enqueue_script('jquery.pw_twitter',
                                  WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/jquery.pw_twitter.js',
                                  array('jquery'),
                                  '1.0.0');
            }
        }

        /**
         *====================================================================
         * deactivate
         *===================================================================*/
        public function deactivate() {
            $this->opt->delete();
        }

        static public function uninstall() {
            $ref = self::getInstance(self::CLASS_NAME);
            $ref->opt->delete();
        }

        /**
         *====================================================================
         * render
         *===================================================================*/
        public function render() {
            $this->opt->load();

            $this->renderStart($this->unique . 'の設定項目');
            if($this->request->isUpdate()){
                $this->renderUpdate('<p>設定を更新しました </p>');
                $this->opt->update($this->request->getAll());
                $this->opt->save();
            }

            $this->renderTableStart();

            //---------------------------------------------------------------------
            $this->renderTableLine();

            // ユーザ名
            $key = self::OPT_USER_NAME;
            $val = $this->opt->get($key);
            $this->renderTableNode(
                'Twitterユーザ名(@*****)',
                '@<input type="text" size="20" name="' . $key . '" value="' . $val . '" />'
                );

            // アイコンキャッシュタイム
            $key = self::OPT_USER_ICON_LIFETIME;
            $val = $this->opt->get($key);
            $this->renderTableNode(
                'アイコンのキャッシュタイム',
                '前回アクセスから<input type="text" size="5" name="' . $key . '" value="' . $val . '" />秒以上経過していたら更新する'
                );

            // アイコンprefix
            $key = self::OPT_USER_ICON_PREFIX;
            $val = $this->opt->get($key);
            $this->renderTableNode(
                'アイコンのprefix',
                '<input type="text" size="20" name="' . $key . '" value="' . $val . '" />'
                );

            //---------------------------------------------------------------------
            $this->renderTableLine();

            // API系
            $tbl = array(
                self::OPT_CONSUMER_KEY          =>      'consumer key',
                self::OPT_CONSUMER_KEY_SECRET   =>      'consumer secret',
                self::OPT_ACCESS_TOKEN          =>      'access token',
                self::OPT_ACCESS_TOKEN_SECRET   =>      'access token secret',
                );

            foreach($tbl as $key => $label){
                $val = $this->opt->get($key);
                $this->renderTableNode(
                    $label,
                    '<input type="text" size="40" name="' . $key . '" value="' . $val . '" />'
                    );
            }

            //---------------------------------------------------------------------
            $this->renderTableLine();

            // ポスト時のつぶやき内容
            $desc  = '<br/><span style="color:#888888;">以下の文字列は右の内容に置換されます</span>';
            $desc .= '<table style="margin:0px;padding:0px;">';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%SITE_NAME%</th><td style="color:#888888;margin:0px;padding:0px;">ブログの名前</td></tr>';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%POST_TITLE%</th><td style="color:#888888;margin:0px;padding:0px;">投稿した記事のタイトル</td></tr>';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%POST_AUTHOR%</th><td style="color:#888888;margin:0px;padding:0px;">投稿した記事の作者</td></tr>';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%POST_EXCERPT%</th><td style="color:#888888;margin:0px;padding:0px;">記事の概要</td></tr>';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%POST_URL%</th><td style="color:#888888;margin:0px;padding:0px;">投稿記事のパーマリンク</td></tr>';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%ADD1%</th><td style="color:#888888;margin:0px;padding:0px;">カスタム追加1</td></tr>';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%ADD2%</th><td style="color:#888888;margin:0px;padding:0px;">カスタム追加2</td></tr>';
            $desc .= '<tr><th style="color:#888888;margin:0px;padding:0px;">%%%</th><td style="color:#888888;margin:0px;padding:0px;">%</td></tr>';
            $desc .= '</table>';

            $key = self::OPT_POST_TWEET_MESSAGE;
            $val = $this->opt->get($key);
            $this->renderTableNode(
                '投稿時につぶやく内容',
                '<textarea cols=40 rows=3 name="' . $key . '">' . $val . '</textarea>' . $desc
                );

            //---------------------------------------------------------------------
            $this->renderTableEnd();

            $this->renderSubmit('変更を保存');
            $this->renderEnd();

            $this->opt->clear();
        }

        /**
         *--------------------------------------------------------------------
         * アイコンサイズの取得
         *-------------------------------------------------------------------*/
        private function get_icon_size($size) {
            switch($size){
            case 'mini':
            case 'normal':
            case 'bigger':
            case 'original':
                break;

            default:
                $size = 'normal';
                break;
            }

            return $size;
        }

        /**
         *--------------------------------------------------------------------
         * アイコン取得
         *-------------------------------------------------------------------*/
        private function get_icon_uri($size, $no_cache) {
            $this->opt->load();
            $size       = $this->get_icon_size($size);
            $user_name  = $this->opt->get(self::OPT_USER_NAME);
            $cache_time = $this->opt->get(self::USER_ICON_CACHE_TIME_BASE . $size);

            // キャッシュの確認
            if($user_name != ''){
                if(false
                   || $no_cache
                   || $cache_time == ''
                   || (time() - $cache_time) > $this->opt->get(self::OPT_USER_ICON_LIFETIME)
                   || !file_exists(dirname(__FILE__) . $this->opt->get(self::USER_ICON_CACHE_PATH_BASE . $size))
                   ){
                    // キャッシュが無いのでアイコンを取得
                    $ret = $this->api->request('GET',
                                               self::END_POINT . 'users/show.json',
                                               array('screen_name' => $user_name),
                                               true,
                                               false
                                               );
                    $ret = (array)json_decode($ret);
                    $uri = $ret['profile_image_url']; // _normal.がついているはず
                    $rep_tbl = array(
                        'normal'   => '_normal.',
                        'bigger'   => '_bigger.',
                        'mini'     => '_mini.',
                        'original' => '.',
                        );
                    $uri = str_replace('_normal.', $rep_tbl[$size], $uri);
                    $curl = curl_init($uri);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_AUTOREFERER, true);
                    $icon   = curl_exec($curl);
                    $format = str_replace('image/', '', curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
                    curl_close($curl);
                    $prefix     = $this->opt->get(self::OPT_USER_ICON_PREFIX);
                    $cache_path = '/icon/' . $prefix . 'user_icon_' . $size . '.' . $format;
                    if(file_put_contents(dirname(__FILE__) . $cache_path, $icon)){
                        $this->opt->set(self::USER_ICON_CACHE_PATH_BASE . $size, $cache_path);
                        $this->opt->set(self::USER_ICON_CACHE_TIME_BASE . $size, time());
                        $this->opt->save();
                    }
                }
                // オプション情報からアイコンパスを取得
                $icon_path = $this->opt->get(self::USER_ICON_CACHE_PATH_BASE . $size);
                if(!file_exists(dirname(__FILE__) . $icon_path)){
                    // キャッシュアイコンが無いみたい
                    $icon_path = "/dummy/user_icon_{$size}.png";
                }
            }else{
                $icon_path = "/dummy/user_icon_{$size}.png";
            }

            $icon_uri = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . $icon_path;

            $this->opt->clear();

            return $icon_uri;
        }

        /**
         *====================================================================
         * ユーザ名の表示
         *===================================================================*/
        static public function dispUserName($opt = array()) {
            $default = array('wrap'             => null,
                             'linkfy'           => false,
                             'link_class'       => '',
                             'echo'             => true,
                             );
            $opt = (object)wp_parse_args($opt, $default);
            $ref = self::getInstance(self::CLASS_NAME);

            $ref->opt->load();

            $user_name = $ref->opt->get(self::OPT_USER_NAME);
            if($opt->linkfy){
                $a = "<a href=\"http://twitter.com/{$user_name}\"";
                if($opt->link_class != ''){
                    $a .= " class=\"{$opt->link_class}\"";
                }
                $a .= '>';
                $ret = $a . $user_name . '</a>';
            }
            $this->opt->clear();

            $user_name = "@{$user_name}";

            if($opt->wrap){
                $user_name = "<{$opt->wrap}>{$user_name}</{$opt->wrap}>";
            }

            if($opt->echo){
                echo $user_name;
            }else{
                return $user_name;
            }
        }

        /**
         *====================================================================
         * アイコン表示
         *===================================================================*/
        static public function dispIcon($opt = array()) {
            $default = array('wrap'             => 'div',
                             'class'            => 'pwtw-icon',
                             'size'             => 'normal',
                             'no_cache'         => false,
                             'only_uri'         => false,
                             'linkfy'           => false,
                             'img_class'        => '',
                             'link_class'       => '',
                             'echo'             => true,
                             );
            $opt     = (object)wp_parse_args($opt, $default);
            $ref     = self::getInstance(self::CLASS_NAME);
            $img_uri = $ref->get_icon_uri($opt->size, $opt->no_cache);

            if($opt->only_uri){
                $ret = $img_uri;
            }else{
                $ret = "<img src=\"{$img_uri}\"";
                if($opt->img_class != ''){
                    $ret .= " class=\"{$opt->img_class}\"";
                }
                $ret .= ' />';
                if($opt->linkfy){
                    $ref->opt->load();
                    $user_name = $ref->opt->get(self::OPT_USER_NAME);
                    $a = "<a href=\"http://twitter.com/{$user_name}\"";
                    if($opt->link_class != ''){
                        $a .= " class=\"{$opt->link_class}\"";
                    }
                    $a .= '>';
                    $ret = $a . $ret . '</a>';
                    $ref->opt->clear();
                }
                if($opt->wrap){
                    $class = '';
                    if($opt->class){
                        $class = " class=\"{$opt->class}\"";
                    }
                    $ret = "<{$opt->wrap}{$class}>{$ret}</{$opt->wrap}>";
                }
            }

            if($opt->echo){
                echo $ret;
            }else{
                return $ret;
            }
        }

        /**
         *====================================================================
         * フォローボタンの表示
         *===================================================================*/
        static public function dispFollowButton($opt = array()) {
            $ref = self::getInstance(self::CLASS_NAME);
            $ref->opt->load();
            $default = array('wrap'             => 'div',
                             'class'            => 'pwtw-followbutton',
                             'user_name'        => $ref->opt->get(self::OPT_USER_NAME),
                             'size'             => null,
                             'is_grey'          => false,
                             'text_color'       => null,
                             'link_color'       => null,
                             'iframe_width'     => null,
                             'iframe_align'     => null,
                             'data_lang'        => 'ja',
                             'data_show_count'  => false,
                             'link_str'         => 'Follow @%USER_NAME%',
                             'echo'             => true,
                             );
            $opt = (object)wp_parse_args($opt, $default);

            $href            = " href=\"https://twitter.com/{$opt->user_name}\"";
            $class           = ' class="twitter-follow-button"';
            $data_size       = (!$opt->size)            ? ''                         : " data-size=\"{$opt->size}\"";
            $data_button     = (!$opt->is_grey)         ? ''                         : ' data-button="grey"';
            $data_text_color = (!$opt->text_color)      ? ''                         : " data-text-color=\"{$opt->text_color}\"";
            $data_link_color = (!$opt->link_color)      ? ''                         : " data-link-color=\"{$opt->link_color}\"";
            $data_width      = (!$opt->iframe_width)    ? ''                         : " data-width=\"{$opt->iframe_width}\"";
            $data_align      = (!$opt->iframe_align)    ? ''                         : " data-align=\"{$opt->iframe_align}\"";
            $data_show_count = (!$opt->data_show_count) ? ' data-show-count="false"' : ' data-show-count="true"';
            $data_lang       = (!$opt->data_lang)       ? ''                         : " data-lang=\"{$opt->data_lang}\"";
            $link_str = str_replace(array('%USER_NAME%', '%%%'),
                                    array($opt->user_name, '%'),
                                    $opt->link_str);

            $ret = "<a{$href}{$class}{$data_size}{$data_button}{$data_text_color}{$data_link_color}{$data_width}{$data_align}{$data_show_count}{$data_lang}>{$link_str}</a>";

            $ref->opt->clear();

            if($opt->wrap){
                $class = '';
                if($opt->class){
                    $class = " class=\"{$opt->class}\"";
                }
                $ret = "<{$opt->wrap}{$class}>{$ret}</{$opt->wrap}>";
            }

            if($opt->echo){
                echo $ret;
            }else{
                return $ret;
            }
        }

        /**
         *====================================================================
         * ツイートの表示
         *===================================================================*/
        static public function dispTweet($opt = array()) {
            $ref = self::getInstance(self::CLASS_NAME);
            $ref->opt->load();
            $default = array('container'        => 'div',
                             'class'            => null,
                             'echo'             => true,
                             //---------------------------------------------------------------------
                             'user_name'        => $ref->opt->get(self::OPT_USER_NAME),
                             'twdefault'        => get_bloginfo('description'),
                             'count'            => 1,
                             'max'              => 1,
                             'negativematch'    => null,
                             'wrap'             => null,
                             'twlinkfy'         => true,
                             'twwrap'           => null,
                             'tmago'            => 'hour',
                             'tmformat'         => "%j月%d日 %A %p%i時%m分",
                             'tmlinkfy'         => true,
                             'tmwrap'           => 'span',
                             'api'              => WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/pw_twitter_timeline.php',
                             );
            $opt = (object)wp_parse_args($opt, $default);

            $container = $opt->container;
            $class     = "pwtw-tweet {$opt->class}";
            $echo      = $opt->echo;
            unset($opt->container, $opt->class, $opt->echo);
            $dataset = json_encode($opt);

            $ret = "<{$container} class=\"{$class}\" data-pwtw='{$dataset}'>{$opt->twdefault}</{$container}>";

            $ref->opt->clear();

            if($echo){
                echo $ret;
            }else{
                return $ret;
            }
        }

        /**
         *====================================================================
         * タイムラインの取得
         *===================================================================*/
        static public function getTimeLine($count = 1) {
            $ref = self::getInstance(self::CLASS_NAME);
            $ref->opt->load();
            $ret = $ref->api->request('GET',
                                      self::END_POINT . 'statuses/user_timeline.json',
                                      array('count' => $count),
                                      true,
                                      false
                                      );
            $ref->opt->clear();
            return $ret;
        }
    }

    
    /**
     *********************************************************************
     * 初期化
     *********************************************************************/
    PW_Twitter::create(PW_Twitter::UNIQUE_KEY,
                       PW_Twitter::CLASS_NAME,
                       __FILE__);
}
