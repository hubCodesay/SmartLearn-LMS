=== SmartLearn LMS ===
Contributors: stabionstudio
Donate link: https://stabion.studio/donate/
Tags: lms, courses, learning, woocommerce, education, elearning, online courses, lessons, training
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional Learning Management System with full WooCommerce integration for selling online courses.

== Description ==

SmartLearn LMS is a powerful and user-friendly Learning Management System for WordPress that seamlessly integrates with WooCommerce to help you create, manage, and sell online courses.

= Key Features =

* **Course Management** - Create unlimited courses with full Gutenberg editor support
* **Lesson System** - Add multiple lessons to each course with easy organization
* **WooCommerce Integration** - Monetize your courses by linking them to WooCommerce products
* **Access Control** - Automatic purchase verification and access management
* **Video Support** - Embed videos from YouTube, Vimeo, or upload your own (HTML5)
* **Free & Paid Content** - Set courses or individual lessons as free preview content
* **Course Categories** - Organize courses with hierarchical categories
* **Difficulty Levels** - Set beginner, intermediate, or advanced levels
* **Responsive Design** - Works perfectly on all devices
* **Shortcodes** - Display courses anywhere with customizable shortcodes
* **User-Friendly** - Intuitive interface for both admins and students
* **Settings Page** - Comprehensive settings with instructions and examples

= Perfect For =

* Online course creators
* Educational institutions
* Training companies
* Coaches and consultants
* Membership sites
* E-learning platforms

= Shortcodes =

Display all courses:
`[courses_list]`

With parameters:
`[courses_list columns="3" category="programming" per_page="9" orderby="date" order="DESC"]`

Display course lessons:
`[course_lessons course_id="123"]`

= Developer Friendly =

* Clean, object-oriented code
* WordPress coding standards
* Hooks and filters for customization
* Well-documented
* Translation ready

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher

= Documentation =

Full documentation available on [GitHub](https://github.com/stabion/smartlearn-lms)

= Support =

For support, please visit our [support page](https://stabion.studio/support/) or create an issue on [GitHub](https://github.com/stabion/smartlearn-lms/issues)

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "SmartLearn LMS"
4. Click "Install Now" and then "Activate"
5. Make sure WooCommerce is installed and activated

= Manual Installation =

1. Download the plugin ZIP file
2. Go to Plugins → Add New → Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin
5. Make sure WooCommerce is installed and activated

= After Activation =

1. Go to Courses → Settings to configure the plugin
2. Create your first course: Courses → Add New
3. Add lessons to your course: Lessons → Add New
4. Link WooCommerce products to courses for monetization
5. Use shortcodes to display courses on any page

== Frequently Asked Questions ==

= Do I need WooCommerce? =

Yes, WooCommerce is required for paid courses. However, you can create free courses without WooCommerce.

= Can I create free courses? =

Yes! You can mark any course as free, and users can access it without purchasing.

= Can I offer free preview lessons? =

Absolutely! You can mark individual lessons as free while keeping the rest of the course paid.

= What video platforms are supported? =

YouTube, Vimeo, and HTML5 video (self-hosted) are all supported.

= Can I customize the design? =

Yes! The plugin uses standard CSS classes that you can override in your theme.

= Is it compatible with my theme? =

Yes, the plugin is designed to work with any properly coded WordPress theme.

= Can I translate the plugin? =

Yes, the plugin is translation ready. You can use .po/.mo files or plugins like Loco Translate.

= How do I display courses on a page? =

Use the `[courses_list]` shortcode on any page or post.

= Does it work with page builders? =

Yes, the shortcodes work with all major page builders including Elementor, Beaver Builder, etc.

= Can students track their progress? =

Progress tracking is planned for version 1.1.0. Currently, students can access purchased courses and navigate through lessons.

= Can I create quizzes? =

Quizzes and assessments are planned for version 1.1.0.

= Is there a PRO version? =

Currently, this is the full version. PRO features may be added in the future based on user feedback.

== Screenshots ==

1. Course list displayed with shortcode - responsive grid layout
2. Single course page with lesson list and access control
3. Single lesson page with video player and navigation
4. Course edit screen in admin with WooCommerce product selector
5. Lesson edit screen with course assignment and video URL
6. Settings page with instructions and shortcode examples
7. Course categories taxonomy for organization
8. Meta boxes showing free/paid options and duration settings

== Changelog ==

= 1.0.0 - 2025-12-10 =
* Initial release
* Course post type with Gutenberg support
* Lesson post type with Gutenberg support
* Course categories taxonomy
* WooCommerce integration for monetization
* Access control based on product purchases
* Video support (YouTube, Vimeo, HTML5)
* Meta boxes for course and lesson settings
* [courses_list] shortcode with parameters
* [course_lessons] shortcode
* Single course template with access gates
* Single lesson template with navigation
* Responsive frontend styles
* Admin styles for meta boxes
* Settings page with documentation
* Translation ready
* Security: nonce verification, capability checks, sanitization
* Free and paid courses/lessons support
* User authentication checks
* Purchase verification through WooCommerce

== Upgrade Notice ==

= 1.0.0 =
Initial release of SmartLearn LMS. A professional Learning Management System with full WooCommerce integration.

== Credits ==

Developed by [Stabion Studio](https://stabion.studio)

== Privacy Policy ==

SmartLearn LMS does not collect, store, or share any user data. All data is stored locally in your WordPress database. The plugin does not communicate with external services except when:

* Embedding videos from YouTube or Vimeo (using their embed codes)
* Verifying WooCommerce purchases (handled by WooCommerce)

== Third Party Services ==

This plugin may interact with the following third-party services:

* **YouTube** - When you embed YouTube videos using video URLs. [YouTube Terms](https://www.youtube.com/t/terms) | [YouTube Privacy](https://policies.google.com/privacy)
* **Vimeo** - When you embed Vimeo videos using video URLs. [Vimeo Terms](https://vimeo.com/terms) | [Vimeo Privacy](https://vimeo.com/privacy)

These services are only used when you explicitly add video URLs to lessons.
