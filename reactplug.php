<?php
/**
 * Plugin.
 *
 * @package Link In Bio
 * @wordpress-plugin
 *
 * Plugin Name:     Link In Bio
 * Description:     Add React app via shortcode and more
 * Author:          Projestic
 * Author URL:
 * Version:         1.0
 */
/**/
function reactshort() {
    return '<div id="root"></div>';
}
// register shortcode
add_shortcode('rack-a-tier', 'reactshort');

add_filter( 'script_loader_tag', function ( $tag, $handle ) {

		if ( 'plugin-react' !== $handle ) {
		return $tag;
	}

	return str_replace( ' src', ' defer src', $tag ); // defer the script
	//return str_replace( ' src', ' async src', $tag ); // OR async the script
	//return str_replace( ' src', ' async defer src', $tag ); // OR do both!

}, 10, 2 );

add_action('wp_enqueue_scripts', 'enq_react', 99); // 99 - adding a priority

function enq_react()
{
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'rack-a-tier') ) {
        function hook_metatag() {
            ?>
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <?php
        }
        add_action('wp_head', 'hook_metatag');
		wp_enqueue_style( 'twd-googlefonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'style-plugin-react', plugin_dir_url( __FILE__ ) . 'build/static/css/main.css' );

		wp_enqueue_script(
			'plugin-react',
			plugin_dir_url( __FILE__ ) . 'build/static/js/main.js',
			[ 'wp-element' ],
		);
	}
}

class PageTemplater {

	/**
	 * A reference to an instance of this class.
	 */
	private static $instance;

	/**
	 * The array of templates that this plugin tracks.
	 */
	protected $templates;

	/**
	 * Returns an instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new PageTemplater();
		}

		return self::$instance;

	}

	/**
	 * Initializes the plugin by setting filters and administration functions.
	 */
	private function __construct() {

		$this->templates = array();

		// Add a filter to the attributes metabox to inject template into the cache.
		if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {

			// 4.6 and older
			add_filter(
				'page_attributes_dropdown_pages_args',
				array( $this, 'register_project_templates' )
			);

		} else {

			// Add a filter to the wp 4.7 version attributes metabox
			add_filter(
				'theme_page_templates', array( $this, 'add_new_template' )
			);

		}

		// Add a filter to the save post to inject out template into the page cache
		add_filter(
			'wp_insert_post_data',
			array( $this, 'register_project_templates' )
		);


		// Add a filter to the template include to determine if the page has our
		// template assigned and return it's path
		add_filter(
			'template_include',
			array( $this, 'view_project_template')
		);


		// Add your templates to this array.
		$this->templates = array(
			'react-template.php' => 'React Template',
		);

	}

	/**
	 * Adds our template to the page dropdown for v4.7+
	 *
	 */
	public function add_new_template( $posts_templates ) {
		$posts_templates = array_merge( $posts_templates, $this->templates );
		return $posts_templates;
	}

	/**
	 * Adds our template to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doens't really exist.
	 */
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list.
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		}

		// New cache, therefore remove the old one
		wp_cache_delete( $cache_key , 'themes');

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	}

	/**
	 * Checks if the template is assigned to the page
	 */
	public function view_project_template( $template ) {

		// Get global post
		global $post;

		// Return template if post is empty
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined
		if ( ! isset( $this->templates[get_post_meta(
				$post->ID, '_wp_page_template', true
			)] ) ) {
			return $template;
		}

		$file = plugin_dir_path( __FILE__ ). get_post_meta(
				$post->ID, '_wp_page_template', true
			);

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} else {
			echo $file;
		}

		// Return template
		return $template;

	}

}
add_action( 'plugins_loaded', array( 'PageTemplater', 'get_instance' ) );

add_action( 'rest_api_init', 'registerUrlForImages');

function registerUrlForImages() {
    register_rest_route('rack-a-tier/v2', 'plugin-dir-url', array(
        'methods' => 'GET',
        'callback' => 'urlResult'
    ));
}

function urlResult() {
    return plugin_dir_url( __FILE__ );
}

// Adding Custom Post Type
add_action( 'init', 'link_in_bio_blog_cpt' );

function link_in_bio_blog_cpt() {

    register_post_type( 'link_in_bio_posts', array(
        'labels' => array(
            'name' => 'Link In Bio Posts',
            'singular_name' => 'Post',
        ),
        'show_in_rest' => true,
        'description' => 'You can create posts for the Link In Bio page',
        'public' => true,
        'menu_position' => 5,
        'supports' => array( 'title', 'editor', 'custom-fields' )
    ));
}

// Creating a local group in ACF
function my_acf_add_local_field_groups() {
    $fieldTitle = array (
        /* (string) Unique identifier for the field. Must begin with 'field_' */
        'key' => 'title',
        /* (string) Visible when editing the field value */
        'label' => 'Title',
        /* (string) Used to save and load data. Single word, no spaces. Underscores and dashes allowed */
        'name' => 'title',
        /* (string) Type of field (text, textarea, image, etc) */
        'type' => 'text',
        /* (string) Instructions for authors. Shown when submitting data */
        'instructions' => '',
        /* (int) Whether or not the field value is required. Defaults to 0 */
        'required' => 0,
        /* (mixed) Conditionally hide or show this field based on other field's values.
        Best to use the ACF UI and export to understand the array structure. Defaults to 0 */
        'conditional_logic' => 0,
        /* (array) An array of attributes given to the field element */
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        /* (mixed) A default value used by ACF if no value has yet been saved */
        'default_value' => '',
    );

    $fieldPrice = array (
        'key' => 'price',
        'label' => 'Price',
        'name' => 'price',
        'type' => 'text',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),

        /* (mixed) A default value used by ACF if no value has yet been saved */
        'default_value' => '',
    );

	$fieldDiscountPrice = array (
        'key' => 'discount_price',
        'label' => 'Discount Price',
        'name' => 'discount_price',
        'type' => 'text',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),

        /* (mixed) A default value used by ACF if no value has yet been saved */
        'default_value' => '',
    );

    $image_field = array(
        'key' => 'image',
        'label' => 'Image',
        'name' => 'image',
        'type' => 'image',
        /* ... Insert generic settings here ... */

        /* (string) Specify the type of value returned by get_field(). Defaults to 'array'.
        Choices of 'array' (Image Array), 'url' (Image URL) or 'id' (Image ID) */
        'return_format' => 'array',

        /* (string) Specify the image size shown when editing. Defaults to 'thumbnail'. */
        'preview_size' => 'thumbnail',

        /* (string) Restrict the image library. Defaults to 'all'.
        Choices of 'all' (All Images) or 'uploadedTo' (Uploaded to post) */
        'library' => 'all',

        /* (int) Specify the minimum width in px required when uploading. Defaults to 0 */
        'min_width' => 0,

        /* (int) Specify the minimum height in px required when uploading. Defaults to 0 */
        'min_height' => 0,

        /* (int) Specify the minimum filesize in MB required when uploading. Defaults to 0
        The unit may also be included. eg. '256KB' */
        'min_size' => 0,

        /* (int) Specify the maximum width in px allowed when uploading. Defaults to 0 */
        'max_width' => 0,

        /* (int) Specify the maximum height in px allowed when uploading. Defaults to 0 */
        'max_height' => 0,

        /* (int) Specify the maximum filesize in MB in px allowed when uploading. Defaults to 0
        The unit may also be included. eg. '256KB' */
        'max_size' => 0,

        /* (string) Comma separated list of file type extensions allowed when uploading. Defaults to '' */
        'mime_types' => '',

    );

    acf_add_local_field_group(array(
        'key' => 'link_in_bio_group_1',
        'title' => 'Fields for creating items',
        'fields' => array (
            $fieldTitle,
            $fieldPrice,
            $fieldDiscountPrice,
            $image_field
        ),
        'location' => array (
            array (
                array (
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'link_in_bio_posts',
                ),
            ),
        ),
    ));

}

add_action('acf/init', 'my_acf_add_local_field_groups');

// Creating a local group in ACF
function my_acf_add_settings_for_link_in_bio() {
    $fieldContactFormId= array (
        /* (string) Unique identifier for the field. Must begin with 'field_' */
        'key' => 'id_cf7',
        /* (string) Visible when editing the field value */
        'label' => 'Contact Form Id',
        /* (string) Used to save and load data. Single word, no spaces. Underscores and dashes allowed */
        'name' => 'link_in_bio_contact_form_id',
        /* (string) Type of field (text, textarea, image, etc) */
        'type' => 'text',
        /* (string) Instructions for authors. Shown when submitting data */
        'instructions' => '',
        /* (int) Whether or not the field value is required. Defaults to 0 */
        'required' => 0,
        /* (mixed) Conditionally hide or show this field based on other field's values.
        Best to use the ACF UI and export to understand the array structure. Defaults to 0 */
        'conditional_logic' => 0,
        /* (array) An array of attributes given to the field element */
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        /* (mixed) A default value used by ACF if no value has yet been saved */
        'default_value' => '',
    );

    $fieldContactFormTitle = array (
        'key' => 'ContactFormTitle',
        'label' => 'Contact Form Title',
        'name' => 'link_in_bio_ContactFormTitle',
        'type' => 'text',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        'default_value' => 'Sign Up to Save',
    );

    $fieldContactFormDescription = array (
        'key' => 'ContactFormDescription',
        'label' => 'Contact Form Description',
        'name' => 'link_in_bio_ContactFormDescription',
        'type' => 'text',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        'default_value' => 'Join our mailing list to get 20% off select products every week!',
    );

    $image_fieldHeaderLogo = array (
        'key' => 'HeaderLogo',
        'label' => 'Header Logo',
        'name' => 'link_in_bio_HeaderLogo',
        'type' => 'image',
        'return_format' => 'array',
        'preview_size' => 'large',
        'library' => 'all',
        'min_width' => 0,
        'min_height' => 0,
        'min_size' => 0,
        'max_width' => 0,
        'max_height' => 0,
        'max_size' => 0,
        'mime_types' => '',
    );

    $fieldFooterCopyright = array (
        'key' => 'FooterCopyright',
        'label' => 'FooterCopyright',
        'name' => 'link_in_bio_FooterCopyright',
        'type' => 'text',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        'default_value' => '© Copyright 2022 Rack-A-Tiers Mfg. Inc. All Rights Reserved.',
    );

    $image_fieldFooterLogo = array (
        'key' => 'FooterLogo',
        'label' => 'Footer Logo',
        'name' => 'link_in_bio_FooterLogo',
        'type' => 'image',
        'return_format' => 'array',
        'preview_size' => 'large',
        'library' => 'all',
        'min_width' => 0,
        'min_height' => 0,
        'min_size' => 0,
        'max_width' => 0,
        'max_height' => 0,
        'max_size' => 0,
        'mime_types' => '',
    );

    $fieldContactUsTitle = array (
        'key' => 'contact_us_title',
        'label' => 'Contact Us Title',
        'name' => 'link_in_bio_contact_us_title',
        'type' => 'text',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),

        /* (mixed) A default value used by ACF if no value has yet been saved */
        'default_value' => 'Contact Us',
    );

    $repeaterContactUsLinks = array(
        'key' => 'field_5c1834534fgf8aContactUs',
        'label' => 'Contact Us Repeater links',
        'name' => 'link_in_bio_repeaterContactUsLinks',
        'type' => 'repeater',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        'collapsed' => '',
        'min' => 0,
        'max' => 0,
        'layout' => 'table',
        'button_label' => '',
        'sub_fields' => array(
            array(
                'key' => 'link_in_bio_repeaterContactUsFaceLinks_ImageKey',
                'label' => 'Image',
                'name' => 'link_in_bio_repeaterContactUsLinks_Image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
                'library' => 'all',
                'min_width' => 0,
                'min_height' => 0,
                'min_size' => 0,
                'max_width' => 0,
                'max_height' => 0,
                'max_size' => 0,
                'mime_types' => '',
            ),
            array (
                'key' => 'link_in_bio_fieldURLFollowUs',
                'label' => 'URL',
                'name' => 'link_in_bio_repeaterContactUsLinks_URL',
                'type' => 'text',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array (
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'value' => '',
            ),
            array (
                'key' => 'link_in_bio_repeaterContactUsLinks_ALT_Key',
                'label' => 'image ALT',
                'name' => 'link_in_bio_repeaterContactUsLinks_ALT',
                'type' => 'text',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array (
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'value' => '',
            )
        ),
    );

    $fieldFollowUsTitle = array (
        'key' => 'FollowUsTitle',
        'label' => 'Follow Us Title',
        'name' => 'link_in_bio_FollowUsTitle',
        'type' => 'text',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array (
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        /* (mixed) A default value used by ACF if no value has yet been saved */
        'default_value' => 'Follow Us',
    );

	$repeaterFollowUsLinks = array(
        'key' => 'field_5c18f8a29941c',
        'label' => 'Repeater Follow Us links',
        'name' => 'link_in_bio_repeaterFollowUsLinks',
        'type' => 'repeater',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
            'width' => '',
            'class' => '',
            'id' => '',
        ),
        'collapsed' => '',
        'min' => 0,
        'max' => 0,
        'layout' => 'table',
        'button_label' => '',
        'sub_fields' => array(
            array(
                'key' => 'image_field_FollowUsFacebook',
                'label' => 'Image',
                'name' => 'link_in_bio_repeaterFollowUsLinks_Image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
                'library' => 'all',
                'min_width' => 0,
                'min_height' => 0,
                'min_size' => 0,
                'max_width' => 0,
                'max_height' => 0,
                'max_size' => 0,
                'mime_types' => '',
            ),
            array (
                'key' => 'link_in_bio_fieldURLFollowUsFacebook',
                'label' => 'URL',
                'name' => 'link_in_bio_repeaterFollowUsLinks_URL',
                'type' => 'text',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array (
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'value' => '',
            ),
            array (
                'key' => 'link_in_bio_repeaterFollowUsLinks_ALT',
                'label' => 'image ALT',
                'name' => 'link_in_bio_repeaterFollowUsLinks_ALT',
                'type' => 'text',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array (
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'value' => '',
            )
        ),
    );

    acf_add_local_field_group(array(
        'key' => 'settings_for_link_in_bio1',
        'title' => 'Fields for Link In Bio plugin',
        'fields' => array (
            $image_fieldHeaderLogo,
            $fieldContactUsTitle,
            $repeaterContactUsLinks,
            $fieldFollowUsTitle,
            $repeaterFollowUsLinks,
            $fieldContactFormId,
            $fieldContactFormTitle,
            $fieldContactFormDescription,
            $fieldFooterCopyright,
            $image_fieldFooterLogo
        ),
        'location' => array (
            array (
                array (
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'settings_for_link_in_bio',
                ),
            ),
        ),
    ));

}

add_action('acf/init', 'my_acf_add_settings_for_link_in_bio');

if (function_exists('acf_add_options_page')) {

    acf_add_options_page(array(
        'page_title'     => 'Link In Bio Settings',
        'menu_title'     => 'Link In Bio Settings',
        'menu_slug'     => 'settings_for_link_in_bio',
        'redirect'         => false
    ));
}

add_action("rest_api_init", function () {
    register_rest_route("acf_options", "/all", [
        "methods" => "GET",
        "callback" => "acf_options_route",
    ]);
});

function acf_options_route() {
    return get_fields('options');
}

// Enabling the REST API For Your ACF Fields in plugin (include all fields in custom posts)
function create_ACF_meta_in_REST() {
    $postypes_to_exclude = ['acf-field-group','acf-field'];
    $extra_postypes_to_include = ["page"];
    $post_types = array_diff(get_post_types(["_builtin" => false], 'names'),$postypes_to_exclude);

    array_push($post_types, $extra_postypes_to_include);

    foreach ($post_types as $post_type) {
        register_rest_field( $post_type, 'ACF', [
                'get_callback'    => 'expose_ACF_fields',
                'schema'          => null,
            ]
        );
    }

}

function expose_ACF_fields( $object ) {
    $ID = $object['id'];
    return get_fields($ID);
}

add_action( 'rest_api_init', 'create_ACF_meta_in_REST' );


add_filter( 'wpcf7_validate_configuration', '__return_false' );