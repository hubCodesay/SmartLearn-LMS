# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-10

### Added
- Initial release
- Course post type with full Gutenberg support
- Lesson post type with full Gutenberg support
- Course categories taxonomy
- WooCommerce integration for monetization
- Access control based on product purchases
- Video support (YouTube, Vimeo, HTML5)
- Meta boxes for course and lesson settings
- `[courses_list]` shortcode with multiple parameters
- `[course_lessons]` shortcode
- Single course template with access gates
- Single lesson template with navigation
- Responsive CSS styles for frontend
- Admin styles for meta boxes
- Settings page with:
  - Configuration options
  - Detailed instructions
  - Shortcode examples
  - Donation section
  - About section
- Complete documentation (README.md)
- Support for free and paid courses/lessons
- User authentication checks
- Purchase verification through WooCommerce
- Automatic redirect for non-logged users
- Admin role always has access

### Features
- Gutenberg block editor support
- Hierarchical course categories
- Course difficulty levels (beginner, intermediate, advanced)
- Course and lesson duration fields
- Thumbnail support for courses
- Video embedding with responsive design
- Lesson ordering (menu_order)
- Access denied messages with appropriate CTAs
- Breadcrumb navigation in lessons
- Previous/Next lesson navigation
- Lock/unlock icons for lessons
- Free lesson badges
- Statistics on settings page (courses/lessons count)

### Technical
- Object-oriented architecture
- Singleton pattern for main class
- Separate classes for functionality:
  - Post Types registration
  - Meta boxes handling
  - Access control
  - Template loading
  - Shortcodes
  - Settings page
- WordPress coding standards
- WooCommerce compatibility checks
- Proper nonce verification
- Capability checks
- Sanitization and escaping
- Translation ready (text domain: smartlearn-lms)
- ABSPATH security checks

### Compatibility
- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Tested up to WordPress 6.4
- Tested up to WooCommerce 8.5

---

## Future Plans

### [1.1.0] - Planned
- [ ] Course progress tracking
- [ ] Quiz and assessments
- [ ] Certificates upon completion
- [ ] Drip content (scheduled lesson release)
- [ ] Course reviews and ratings
- [ ] Student dashboard
- [ ] Instructor profiles
- [ ] Course prerequisites

### [1.2.0] - Planned
- [ ] Elementor widget support
- [ ] Email notifications
- [ ] Advanced analytics
- [ ] Bulk operations
- [ ] Export/import courses
- [ ] Course bundles

### [2.0.0] - Future
- [ ] Mobile app integration
- [ ] Live classes support
- [ ] Discussion forums
- [ ] Gamification (badges, points)
- [ ] Advanced reporting
- [ ] Multi-instructor support

---

For more information, visit [stabion.studio](https://stabion.studio)
