# 🚀 Pull Request

## 📋 Description
<!-- Briefly describe what this PR does -->

## 🎯 Type of Change
<!-- Mark the relevant option with an [x] -->

- [ ] 🐛 Bug fix (non-breaking change which fixes an issue)
- [ ] ✨ New feature (non-breaking change which adds functionality)
- [ ] 💥 Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] 📚 Documentation update
- [ ] 🔧 Code refactoring
- [ ] 🧪 Test improvements
- [ ] 🎨 UI/UX improvements

## 🧪 Testing
<!-- Describe how you tested your changes -->

- [ ] I have tested this locally
- [ ] I have added/updated tests where appropriate
- [ ] All existing tests pass
- [ ] The plugin activates without errors
- [ ] LLMS.txt generation works correctly

## ✅ Checklist

### 🐘 PHP 8.3+ Requirements
- [ ] Code uses strict type declarations (`declare(strict_types=1);`)
- [ ] All functions have proper return type declarations
- [ ] Properties use typed declarations where appropriate
- [ ] No deprecated PHP features are used
- [ ] Code is compatible with PHP 8.3+

### 🛡️ WordPress Security
- [ ] All files start with `if (!defined('ABSPATH')) { exit; }`
- [ ] User input is properly sanitized
- [ ] Output is properly escaped (esc_html, esc_attr, esc_url)
- [ ] Database queries use `$wpdb->prepare()`
- [ ] AJAX handlers verify nonces
- [ ] Capability checks are in place (`current_user_can()`)

### 📏 Code Quality
- [ ] Code follows WordPress coding standards
- [ ] Variable names are descriptive
- [ ] Functions are properly documented
- [ ] No debug code (var_dump, console.log, etc.) is left in
- [ ] Error handling is implemented where appropriate

### 🔧 WordPress Compatibility
- [ ] Compatible with WordPress 6.7+
- [ ] Uses WordPress APIs instead of direct database access where possible
- [ ] Hooks are used appropriately
- [ ] No conflicts with common plugins tested

## 📸 Screenshots
<!-- Add screenshots if your changes affect the UI -->

## 🔗 Related Issues
<!-- Link any related issues here -->
Fixes #(issue number)

## 📝 Additional Notes
<!-- Any additional information that reviewers should know -->

---

### 🤖 For Maintainers
- [ ] Version number needs updating
- [ ] CHANGELOG.md needs updating
- [ ] Release notes prepared
- [ ] Breaking changes documented