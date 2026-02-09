# Using Include Files

## Header, Navbar, and Footer Components

The platform now uses separated, reusable components for better maintainability.

### Usage in Your Pages

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

Auth::requireLogin();

// Set page-specific variables
$pageTitle = 'Dashboard - PlacementCode';
$additionalCSS = '
    .custom-class { color: #ffa116; }
';

// Include header and navbar
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<!-- Your page content here -->
<div class="container">
    <h1>Your Content</h1>
</div>

<?php
// Include footer
include __DIR__ . '/../../includes/footer.php';
?>
```

### Components

**header.php**
- HTML doctype and head section
- Meta tags
- Google Fonts
- Base styling for navbar
- Mobile responsive CSS
- Supports `$pageTitle` and `$additionalCSS` variables

**navbar.php**
- Navigation menu with active state detection
- User avatar with initial
- Mobile hamburger menu
- Auto-detects current page for active highlighting

**footer.php**
- Footer with links
- Contact information
- Copyright notice
- Responsive grid layout

### Benefits

✅ **Consistency**: Same header/footer across all pages
✅ **Maintainability**: Update once, changes everywhere
✅ **Clean Code**: Pages focus on content, not boilerplate
✅ **Mobile Ready**: Responsive design built-in
✅ **Easy Customization**: Use variables for page-specific needs
