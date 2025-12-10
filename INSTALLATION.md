# Installation Guide

## How to Install SmartLearn LMS

### From WordPress.org (Recommended)

1. Log in to your WordPress admin panel
2. Navigate to **Plugins → Add New**
3. Search for **"SmartLearn LMS"**
4. Click **"Install Now"**
5. Click **"Activate"**
6. Make sure WooCommerce is installed and activated

### Manual Installation from ZIP

1. Download the plugin ZIP file from [WordPress.org](https://wordpress.org/plugins/smartlearn-lms/) or [GitHub Releases](https://github.com/stabion/smartlearn-lms/releases)
2. Log in to your WordPress admin panel
3. Navigate to **Plugins → Add New → Upload Plugin**
4. Click **"Choose File"** and select the ZIP file
5. Click **"Install Now"**
6. Click **"Activate Plugin"**
7. Make sure WooCommerce is installed and activated

### Via FTP

1. Download and extract the plugin ZIP file
2. Upload the `smartlearn-lms` folder to `/wp-content/plugins/` directory
3. Log in to your WordPress admin panel
4. Navigate to **Plugins**
5. Find "SmartLearn LMS" and click **"Activate"**
6. Make sure WooCommerce is installed and activated

## After Installation

### Quick Setup (5 minutes)

1. **Configure Settings**
   - Go to **Courses → Settings**
   - Set your login URL (default: /my-account/)
   - Customize button texts if needed
   - Review shortcode examples

2. **Create Your First Course**
   - Go to **Courses → Add New**
   - Enter course title and description
   - Add course thumbnail (recommended)
   - Choose if course is free or select WooCommerce product
   - Set difficulty level and duration
   - Click **"Publish"**

3. **Add Lessons**
   - Go to **Lessons → Add New**
   - Enter lesson title and content
   - Select the course this lesson belongs to
   - Add video URL if you have one (YouTube/Vimeo/HTML5)
   - Mark as free for preview (optional)
   - Click **"Publish"**

4. **Display Courses**
   - Create a new page (e.g., "Courses" or "Learn")
   - Add the shortcode: `[courses_list]`
   - Publish the page
   - View your courses!

## Requirements

- **WordPress**: 5.8 or higher
- **WooCommerce**: 5.0 or higher (for paid courses)
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

## Troubleshooting

### Plugin doesn't activate
- Check PHP version (must be 7.4+)
- Make sure WordPress is updated to 5.8+
- Check for conflicting plugins

### Courses don't display
- Make sure you've published at least one course
- Check shortcode syntax: `[courses_list]`
- Clear cache if using caching plugin

### Access control not working
- Make sure WooCommerce is active
- Verify product is linked to course
- Check user has purchased the product
- Test with admin account (admins always have access)

### Video not showing
- Verify video URL is correct
- Check YouTube/Vimeo video is public
- Try different video format

## Need Help?

- 📖 [Full Documentation](https://github.com/stabion/smartlearn-lms)
- 🐛 [Report Issue](https://github.com/stabion/smartlearn-lms/issues)
- 💬 [Support Forum](https://wordpress.org/support/plugin/smartlearn-lms/)
- 📧 [Email Support](mailto:support@stabion.studio)

## What's Next?

- Create more courses and lessons
- Set up WooCommerce products for paid courses
- Customize styles in your theme
- Add course categories for organization
- Explore all shortcode parameters
