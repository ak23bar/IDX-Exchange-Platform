# Contributing to Full-Stack MLS Property Platform

Thank you for your interest in contributing to the IDXExchange Full-Stack MLS Property Platform! This document provides guidelines for SD6 team members and external contributors.

## 🤝 Team Structure

**Team Lead:** Akbar Aman  
**Team Size:** 6 members (SD6)  
**Organization:** IDXExchange  
**Cohort:** Fall 2025

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Commit Guidelines](#commit-guidelines)
- [Pull Request Process](#pull-request-process)
- [Testing Requirements](#testing-requirements)
- [Documentation](#documentation)

---

## 📜 Code of Conduct

### Our Pledge

- **Respectful collaboration**: Treat all team members with respect
- **Constructive feedback**: Provide helpful, actionable code reviews
- **Knowledge sharing**: Help teammates learn and grow
- **Quality focus**: Prioritize code quality and maintainability

### Expected Behavior

✅ **DO:**
- Ask questions when uncertain
- Document your code thoroughly
- Test changes before submitting PRs
- Respond to code review feedback promptly
- Keep discussions professional and focused

❌ **DON'T:**
- Commit directly to `main` branch
- Push credentials or sensitive data
- Ignore linting/formatting rules
- Make breaking changes without discussion

---

## 🚀 Getting Started

### 1. Fork & Clone

```bash
# Fork the repository on GitHub
git clone https://github.com/YOUR_USERNAME/idx-exchange-platform.git
cd idx-exchange-platform

# Add upstream remote
git remote add upstream https://github.com/idxexchange/idx-exchange-platform.git
```

### 2. Set Up Development Environment

```bash
# Copy environment template
cp .env.example .env

# Edit with your local credentials
nano .env

# Install dependencies (if using Composer)
composer install
```

### 3. Create Feature Branch

```bash
# Update from main
git checkout main
git pull upstream main

# Create feature branch
git checkout -b feature/your-feature-name
```

---

## 🔄 Development Workflow

### Branch Naming Convention

Follow this pattern: `<type>/<short-description>`

**Types:**
- `feature/` - New features (e.g., `feature/map-view`)
- `fix/` - Bug fixes (e.g., `fix/search-pagination`)
- `refactor/` - Code refactoring (e.g., `refactor/api-client`)
- `docs/` - Documentation updates (e.g., `docs/api-guide`)
- `test/` - Test additions/improvements (e.g., `test/search-unit-tests`)
- `chore/` - Maintenance tasks (e.g., `chore/update-dependencies`)

**Examples:**
```bash
feature/user-authentication
fix/photo-gallery-loading
refactor/database-queries
docs/deployment-guide
```

### Workflow Steps

1. **Create branch** from latest `main`
2. **Make changes** following coding standards
3. **Test locally** (see Testing Requirements)
4. **Commit** with descriptive messages
5. **Push** to your fork
6. **Open Pull Request** against `main`
7. **Address review feedback**
8. **Merge** after approval

---

## 💻 Coding Standards

### PHP Standards (PSR-12)

```php
<?php
declare(strict_types=1);

namespace IDXExchange\Models;

/**
 * Property model representing MLS listing data.
 * 
 * @package IDXExchange\Models
 * @author SD6 Team
 */
class Property
{
    private string $listingId;
    private ?float $price;
    
    /**
     * Constructor.
     *
     * @param string $listingId Unique listing identifier
     * @param float|null $price Property price in USD
     */
    public function __construct(string $listingId, ?float $price = null)
    {
        $this->listingId = $listingId;
        $this->price = $price;
    }
    
    /**
     * Get formatted price.
     *
     * @return string Price formatted as USD currency
     */
    public function getFormattedPrice(): string
    {
        return $this->price !== null 
            ? '$' . number_format($this->price, 0) 
            : 'N/A';
    }
}
```

**Key Rules:**
- ✅ Use type declarations (`declare(strict_types=1)`)
- ✅ Add PHPDoc comments for all functions
- ✅ Use camelCase for variables/methods
- ✅ Use PascalCase for classes
- ✅ 4 spaces for indentation (no tabs)
- ✅ Always use prepared statements for SQL

### JavaScript Standards (ES6+)

```javascript
/**
 * Toggle favorite status for a property.
 * @param {string} listingId - Unique listing identifier
 * @returns {Promise<boolean>} Success status
 */
async function toggleFavorite(listingId) {
  try {
    const response = await fetch(`/api/favorites/${listingId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    return await response.json();
  } catch (error) {
    console.error('Failed to toggle favorite:', error);
    return false;
  }
}
```

**Key Rules:**
- ✅ Use `const`/`let` (never `var`)
- ✅ Use arrow functions where appropriate
- ✅ Add JSDoc comments
- ✅ Use async/await for promises
- ✅ Handle errors gracefully

### SQL Standards

```sql
-- Good: Descriptive, formatted, with comments
SELECT 
  p.L_ListingID,
  p.L_Address,
  p.L_City,
  p.L_SystemPrice,
  p.L_UpdateDate
FROM 
  rets_property AS p
WHERE 
  p.L_City = :city
  AND p.L_SystemPrice BETWEEN :min_price AND :max_price
ORDER BY 
  p.L_UpdateDate DESC
LIMIT 
  :limit OFFSET :offset;

-- Bad: No formatting, unclear
select * from rets_property where L_City='LA' and L_SystemPrice>500000;
```

**Key Rules:**
- ✅ Use uppercase for SQL keywords
- ✅ Use descriptive table aliases
- ✅ Always use parameterized queries
- ✅ Add comments for complex queries
- ✅ Format multi-line queries for readability

---

## 📝 Commit Guidelines

### Commit Message Format

```
<type>(<scope>): <subject>

<body (optional)>

<footer (optional)>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `refactor`: Code restructuring without behavior change
- `docs`: Documentation changes
- `style`: Formatting, missing semicolons, etc.
- `test`: Adding or updating tests
- `chore`: Maintenance tasks, dependency updates

### Examples

**Simple commit:**
```
feat(search): add ZIP code filter to property search
```

**Detailed commit:**
```
fix(api): resolve token refresh race condition

The token refresh logic was causing concurrent requests to fail
when the token expired during high traffic. Implemented a mutex
lock using app_state table to prevent simultaneous refresh attempts.

Fixes #42
```

**Breaking change:**
```
refactor(db)!: rename L_Keyword2 to bedrooms

BREAKING CHANGE: Database column L_Keyword2 renamed to bedrooms
for improved clarity. Update all queries and migrate existing data.

Migration script: migrations/2025_01_rename_bedrooms.sql
```

### Rules

- ✅ Use imperative mood ("add" not "added")
- ✅ Keep subject line under 72 characters
- ✅ Reference issue numbers when applicable
- ✅ Explain "why" not just "what" in body

---

## 🔍 Pull Request Process

### 1. Before Opening PR

**Checklist:**
- [ ] Code follows style guidelines
- [ ] All tests pass locally
- [ ] No merge conflicts with `main`
- [ ] Sensitive data removed/sanitized
- [ ] Documentation updated if needed
- [ ] Self-review completed

### 2. PR Title Format

Same as commit message format:
```
feat(search): add advanced filtering options
```

### 3. PR Description Template

```markdown
## 📋 Description
Brief summary of changes and motivation.

## 🔗 Related Issues
Fixes #123
Relates to #456

## 🧪 Testing
- [ ] Tested on Chrome/Firefox
- [ ] Tested responsive design
- [ ] Tested with 1000+ listings
- [ ] API endpoints verified

## 📸 Screenshots (if applicable)
[Attach before/after screenshots]

## ⚠️ Breaking Changes
None / List any breaking changes

## 📝 Checklist
- [ ] Code follows project style
- [ ] Self-reviewed code
- [ ] Commented complex logic
- [ ] Updated documentation
- [ ] No console warnings/errors
```

### 4. Review Process

**Reviewers will check:**
- Code quality and style compliance
- Security vulnerabilities
- Performance implications
- Test coverage
- Documentation accuracy

**Response Time:**
- Team members should review within **24-48 hours**
- Address feedback within **2-3 days**

### 5. Approval & Merge

- **Minimum 1 approval** required (2 for critical changes)
- Team lead has final approval authority
- Squash commits when merging to keep history clean

---

## 🧪 Testing Requirements

### Manual Testing

**Before submitting PR, test:**
1. **Search functionality**: All filter combinations
2. **Property details**: Modal loading, image gallery
3. **Favorites**: Add/remove, session persistence
4. **CSV export**: Data accuracy
5. **Responsive design**: Mobile, tablet, desktop

### Automated Testing (Future)

We plan to implement:
- PHPUnit for backend logic
- Jest for JavaScript
- Selenium for E2E tests

---

## 📚 Documentation

### When to Update Docs

✅ **Update documentation when:**
- Adding new features
- Changing API endpoints
- Modifying database schema
- Updating configuration requirements
- Changing deployment process

### Documentation Locations

- `README.md` - Project overview, setup
- `docs/DATABASE.md` - Schema details
- `docs/API.md` - API integration guide
- `docs/DEPLOYMENT.md` - Production deployment
- Code comments - Inline explanations

---

## 🆘 Getting Help

### Resources

- **Team Chat**: [Slack/Discord channel]
- **Team Lead**: Akbar Aman - [email/contact]
- **Documentation**: `/docs` folder
- **Issues**: GitHub Issues tab

### Questions?

1. Check existing documentation
2. Search closed issues/PRs
3. Ask in team chat
4. Create GitHub discussion
5. Contact team lead

---

## 🏆 Recognition

Outstanding contributors will be recognized in:
- Monthly team meetings
- README acknowledgments
- Project presentations
- LinkedIn recommendations

---

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

**Thank you for contributing to IDXExchange! 🚀**

*Built with ❤️ by SD6 Team*
