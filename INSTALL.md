# Ctrl+Find - Advanced Plugin Search

## Installation Guide

### Quick Installation

1. **Download the plugin files** to your WordPress site's `/wp-content/plugins/ctrl-find/` directory
2. **Activate the plugin** through WordPress Admin > Plugins
3. **Navigate to Plugins > Add New** to see the enhanced filtering interface

### Manual Installation

1. Upload the entire `ctrl-find` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The enhanced filters will automatically appear on the plugin installation page

### Features

- **Author Filter**: Search by WordPress.org username
- **Tag Filter**: Filter by plugin tags with popular options dropdown
- **Rating Filter**: Show plugins with minimum star ratings
- **Active Installs**: Filter by minimum active installations
- **Last Updated**: Show recently updated plugins
- **Compatibility**: Show only WordPress-compatible plugins
- **Results Per Page**: Choose display count (24, 48, 96)
- **Active Filter Display**: See and remove individual filters
- **URL Persistence**: Share filtered searches via URL
- **Keyboard Shortcuts**: Ctrl+F to focus search
- **Responsive Design**: Works on all devices

### Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Admin access to install plugins

### Security

- All inputs are sanitized and validated
- Nonces protect all forms
- Capability checks ensure proper access
- Follows WordPress security best practices

### Performance

- Uses WordPress core functions exclusively
- Efficient caching with transients
- Conditional asset loading
- Minimal impact on page load times

### Support

For support, feature requests, or bug reports, please visit the plugin's GitHub repository or WordPress.org plugin page.

### License

This plugin is licensed under the GPL v2 or later license.
