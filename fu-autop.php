<?php
/**
 * Plugin Name: FU Autop
 * Plugin URI: https://github.com/AgencyPMG/FU-Autop
 * Description: Sometimes wpautop is a pain in the ass.
 * Version: 1.0
 * Text Domain: fuap
 * Author: Christopher Davis
 * Author URI: https://www.pmg.com
 * License: MIT
 *
 * Copyright (c) 2015 PMG <https://www.pmg.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 * @category    WordPress
 * @author      Christopher Davis <http://christopherdavis.me>
 * @copyright   2014 Christopher Davis
 * @license     http://opensource.org/licenses/MIT MIT
 */

FU_Autop::init();

class FU_Autop
{
    const META_KEY = '_fuap_on';

    const NONCE = '_fuap_nonce';

    private static $ins = null;

    public static function init()
    {
        add_action('plugins_loaded', array(__CLASS__, 'instance'));
    }

    public static function instance()
    {
        is_null(self::$ins) && self::$ins = new self;
        return self::$ins;
    }

    private function __construct()
    {
        add_action('add_meta_boxes', array($this, 'box'), 10, 2);
        add_action('save_post', array($this, 'save'), 10, 2);
        add_action('loop_start', array($this, 'unautop'));
        add_action('loop_end', array($this, 'reautop'));
    }

    public  function box($post_type, $post)
    {
        if(!in_array($post_type, $this->get_allowed_types()))
            return;

        add_meta_box(
            'fu-autop',
            __('FU Autop', 'fuap'),
            array($this, 'box_cb'),
            $post_type,
            'side',
            'low'
        );
    }

    public function box_cb($post)
    {
        wp_nonce_field(self::NONCE . $post->ID, self::NONCE, false);
        printf(
            '<input type="checkbox" name="%1$s" id="%1$s" value="%1$s" %2$s />'.
            ' <label for="%1$s">%3$s</label>',
            esc_attr(self::META_KEY),
            checked('on', get_post_meta($post->ID, static::META_KEY, true), false),
            esc_html__('No Autop', 'fuap')
        );
    }

    public function save($post_id, $post)
    {
        if(!in_array($post->post_type, $this->get_allowed_types()))
            return;

        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;

        if(
            !isset($_POST[self::NONCE]) ||
            !wp_verify_nonce($_POST[self::NONCE], self::NONCE . $post_id)
        ) return;

        $cap = get_post_type_object($post->post_type)->cap->edit_post;
        if(!current_user_can($cap, $post_id))
            return;

        update_post_meta($post_id, self::META_KEY,
            !empty($_POST[self::META_KEY]) ? 'on' : 'off');
    }

    public function unautop($q)
    {
        global $post;

        if(
            !is_singular($this->get_allowed_types()) ||
            !$q->is_main_query() ||
            'on' != get_post_meta($post->ID, self::META_KEY, true)
        ) return;

        remove_filter('the_content', 'wpautop');
    }

    public function reautop($q)
    {
        if(
            !is_singular($this->get_allowed_types()) ||
            !$q->is_main_query() ||
            has_filter('the_content', 'wpautop')
        ) return;

        add_filter('the_content', 'wpautop');
    }

    private function get_allowed_types()
    {
        return apply_filters('fu_autop_allowed_types', array('page'));
    }
}
