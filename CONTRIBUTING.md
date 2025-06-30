# 🤝 Contributing to WP LLMs.txt

Thank you for your interest in contributing to WP LLMs.txt! This guide will help you get started with contributing to our modern WordPress plugin that makes websites AI-discoverable.

## 🚀 Quick Start

1. **Fork** this repository
2. **Clone** your fork locally
3. **Create** a new branch for your feature/fix
4. **Make** your changes following our guidelines
5. **Test** your changes thoroughly
6. **Submit** a pull request

## 🎯 Development Requirements

### 🐘 PHP Requirements
- **PHP 8.3+** (strict requirement)
- Modern PHP features encouraged:
  - Strict type declarations (`declare(strict_types=1);`)
  - Typed properties and return types
  - Readonly properties where appropriate
  - Union types when beneficial

### 🔧 WordPress Requirements
- **WordPress 6.7+** minimum
- Follow WordPress coding standards
- Use WordPress APIs instead of direct implementations
- Maintain backward compatibility when possible

### 🔄 Auto-Update System
- Plugin includes GitHub-based auto-update functionality
- Updates check `https://api.github.com/repos/tomrobak/website-llms-txt/releases/latest`
- Ensure version numbers follow semantic versioning
- Tag releases properly on GitHub for auto-updates to work

### 🛠️ Development Environment
- Local WordPress installation
- PHP 8.3+ with extensions: `mbstring`, `xml`, `json`, `curl`
- Git for version control
- Code editor with PHP syntax checking

## 📏 Code Standards

### 🐘 PHP Code Style

```php
<?php
/**
 * File description
 * 
 * @package WP_LLMs_txt
 * @since 2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Example_Class {
    private readonly string $property;
    
    public function __construct(string $value) {
        $this->property = $value;
    }
    
    public function get_value(): string {
        return $this->property;
    }
}
```

### 🛡️ Security Requirements

**Every PHP file MUST:**
- Start with `if (!defined('ABSPATH')) { exit; }`
- Sanitize all user input using WordPress functions
- Escape all output using `esc_html()`, `esc_attr()`, `esc_url()`
- Use `$wpdb->prepare()` for database queries
- Verify nonces for AJAX requests
- Check user capabilities with `current_user_can()`

### 📋 Documentation Standards

```php
/**
 * Brief description of the function
 * 
 * Longer description if needed explaining the purpose,
 * behavior, and any important notes.
 * 
 * @since 2.0
 * @param string $param1 Description of parameter
 * @param int    $param2 Description of parameter
 * @return bool Description of return value
 */
public function example_function(string $param1, int $param2): bool {
    // Implementation
}
```

## 🧪 Testing Your Changes

### 🔍 Before Submitting
1. **Syntax Check**: All PHP files must pass `php -l filename.php`
2. **WordPress Activation**: Plugin must activate without errors
3. **Basic Functionality**: LLMS.txt generation must work
4. **Error Log**: No PHP warnings or errors in debug.log
5. **Multi-PHP**: Test on PHP 8.3 and 8.4 if possible

### 🚨 Automatic Checks
Our GitHub Actions pipeline runs on releases:
- PHP syntax validation
- WordPress plugin structure checks
- Version compatibility verification
- Automated release package creation
- Asset upload to GitHub releases

## 🎯 Contribution Types

### 🐛 Bug Fixes
- Include steps to reproduce
- Explain the expected vs actual behavior
- Test fix thoroughly
- Add regression test if possible

### ✨ New Features
- Discuss in an issue first
- Ensure it fits the plugin's scope
- Include documentation updates
- Consider performance impact
- Maintain compatibility

### 📚 Documentation
- Keep it clear and concise
- Include code examples
- Update CHANGELOG.md
- Use emojis consistently 😊

### 🎨 UI/UX Improvements
- Follow shadcn/ui design principles with zinc/neutral color palette
- Ensure accessibility (WCAG compliance)
- Test on different screen sizes
- Maintain consistency with existing UI
- Avoid colorful elements - keep it professional and neutral

## 🔄 Pull Request Process

### 📋 PR Checklist
- [ ] Descriptive title and description
- [ ] Links to related issues
- [ ] All CI checks pass
- [ ] Changes tested locally
- [ ] Documentation updated if needed
- [ ] CHANGELOG.md updated for significant changes

### 🎯 Review Process
1. **Automated checks** must pass
2. **Code review** by maintainers
3. **Testing** on different environments
4. **Approval** and merge

## 🚧 Development Workflow

### 🌿 Branch Naming
- `feature/description` - New features
- `fix/description` - Bug fixes  
- `docs/description` - Documentation updates
- `refactor/description` - Code improvements

### 📝 Commit Messages
```
feat: add new LLMS.txt generation feature

- Implement custom post type filtering
- Add user interface for configuration
- Include comprehensive error handling
- Update documentation with examples
```

Note: If you used Claude Code for development, add:
```
🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### 🏷️ Semantic Versioning
- `MAJOR.MINOR.PATCH` (e.g., 2.1.0)
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

## 🤔 Questions & Help

### 💬 Where to Ask
- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: General questions and ideas
- **Code Review**: Specific implementation questions in PRs

### 📖 Useful Resources
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [PHP 8.3 Documentation](https://www.php.net/manual/en/)
- [LLMS.txt Specification](https://llms-txt.org/)

## 🙏 Code of Conduct

- **Be respectful** and professional
- **Help others** learn and grow
- **Focus on** constructive feedback
- **Celebrate** diverse perspectives
- **Have fun** building something awesome! 🎉

## 🎉 Recognition

Contributors will be:
- Added to our contributors list
- Mentioned in release notes for significant contributions
- Credited in the plugin's documentation

---

## 🚀 Ready to Contribute?

1. Check our [open issues](../../issues) for inspiration
2. Join the discussion in [GitHub Discussions](../../discussions)
3. Fork the repo and start coding!

**Thank you for making WP LLMs.txt better for everyone!** 🤖✨