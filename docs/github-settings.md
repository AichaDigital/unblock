# GitHub Repository Settings Guide

This document explains the recommended GitHub settings for the Unblock project.

## 🔒 Branch Protection Rules

### Protecting the `main` Branch

**⚠️ Important**: Branch protection rules must be configured via GitHub web interface, as they cannot be set via CLI for free/personal accounts.

To configure branch protection:

1. Go to: https://github.com/AichaDigital/unblock/settings/branches
2. Click "Add branch protection rule"
3. Branch name pattern: `main`
4. Enable these settings:

#### Required Settings
- ✅ **Require a pull request before merging**
  - Require approvals: 1 (or more for team projects)
  - Dismiss stale pull request approvals when new commits are pushed
  - Require review from Code Owners (optional, if you have a CODEOWNERS file)

- ✅ **Require status checks to pass before merging**
  - Require branches to be up to date before merging
  - Status checks required:
    - `quality` (from CI workflow)
    - Or all checks if you add more workflows

- ✅ **Require conversation resolution before merging**
  - Ensures all review comments are addressed

- ✅ **Require signed commits** (Recommended)
  - Ensures commits are cryptographically signed

- ✅ **Include administrators**
  - Apply rules to repository administrators too
  - **Important**: This prevents even you from accidentally pushing directly to main

#### Optional but Recommended
- ✅ **Require linear history**
  - Forces use of rebase or squash merge
  - Keeps commit history clean

- ✅ **Do not allow bypassing the above settings**
  - Ensures protection rules are always enforced

- ❌ **Allow force pushes** - Keep DISABLED
- ❌ **Allow deletions** - Keep DISABLED

### Additional Branches to Protect

If you create `develop` or `staging` branches, apply similar rules.

## 🔧 Repository Settings

### General Settings

Go to: https://github.com/AichaDigital/unblock/settings

#### Features
- ✅ **Issues**: Enabled
- ✅ **Discussions**: Enabled
- ❌ **Wiki**: Disabled (we use `/docs` instead)
- ✅ **Projects**: Enabled (optional, for roadmap)

#### Pull Requests
- ✅ **Allow squash merging**
  - Default to pull request title and description
- ✅ **Allow rebase merging**
- ❌ **Allow merge commits** - Disabled for clean history
- ✅ **Automatically delete head branches** - Clean up after PR merge
- ✅ **Always suggest updating pull request branches**

#### Pushes
- ❌ **Limit how many branches and tags can be updated in a single push** - Disabled

### Security & Analysis

Go to: https://github.com/AichaDigital/unblock/settings/security_analysis

- ✅ **Dependency graph**: Enabled
- ✅ **Dependabot alerts**: Enabled (automatic security alerts)
- ✅ **Dependabot security updates**: Enabled (automatic PR for vulnerabilities)
- ✅ **Dependabot version updates**: Consider enabling (creates PRs for dependency updates)
- ✅ **Secret scanning**: Enabled (GitHub Advanced Security - free for public repos)
- ✅ **Code scanning**: Consider enabling CodeQL for automatic code analysis

## 📋 Dependabot Configuration

Create `.github/dependabot.yml` to keep dependencies updated:

```yaml
version: 2
updates:
  # PHP Composer dependencies
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
    open-pull-requests-limit: 5
    labels:
      - "dependencies"
      - "php"

  # JavaScript dependencies (pnpm)
  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"
    open-pull-requests-limit: 5
    labels:
      - "dependencies"
      - "javascript"

  # GitHub Actions
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
    labels:
      - "dependencies"
      - "github-actions"
```

## 🏷️ Labels

Recommended labels for issues and PRs:

### Type
- `bug` - Something isn't working
- `enhancement` - New feature or request
- `documentation` - Documentation improvements
- `question` - Further information is requested
- `security` - Security-related issue

### Priority
- `priority: critical` - Drop everything and fix
- `priority: high` - Important
- `priority: medium` - Normal priority
- `priority: low` - Nice to have

### Status
- `needs-triage` - Needs initial review
- `needs-investigation` - Requires more info
- `in-progress` - Being worked on
- `blocked` - Cannot proceed
- `wont-fix` - Will not be addressed

### Area
- `area: firewall` - Firewall analysis logic
- `area: whmcs` - WHMCS integration
- `area: auth` - Authentication/authorization
- `area: ui` - User interface
- `area: api` - API/backend
- `area: tests` - Testing infrastructure

### Other
- `good first issue` - Good for newcomers
- `help wanted` - Community help appreciated
- `dependencies` - Dependency updates
- `breaking-change` - Requires major version bump

## 👥 Collaborators & Teams

### Adding Collaborators

For contributors you trust:
1. Go to: https://github.com/AichaDigital/unblock/settings/access
2. Click "Add people"
3. Choose appropriate role:
   - **Read**: Can view and clone
   - **Triage**: Can manage issues and PRs (no code push)
   - **Write**: Can push to non-protected branches
   - **Maintain**: Can manage repository settings
   - **Admin**: Full access

### Code Owners (Optional)

Create `.github/CODEOWNERS` to auto-assign reviewers:

```
# Global owners
* @your-github-username

# Specific areas
/app/Actions/ @firewall-expert
/app/Services/Firewall/ @firewall-expert
/docs/ @documentation-team
/.github/ @devops-team
```

## 🤖 GitHub Apps (Recommended)

Consider installing these GitHub Apps:

1. **Codecov** - Test coverage reporting
   - https://github.com/apps/codecov

2. **Renovate** - Alternative to Dependabot, more powerful
   - https://github.com/apps/renovate

3. **Semantic Pull Requests** - Enforce conventional commits
   - https://github.com/apps/semantic-pull-requests

4. **WIP** - Prevent merging work-in-progress PRs
   - https://github.com/apps/wip

## 📊 Insights & Analytics

Enable these for better project management:

1. **Pulse**: Shows activity overview
   - https://github.com/AichaDigital/unblock/pulse

2. **Contributors**: Shows contributor statistics
   - https://github.com/AichaDigital/unblock/graphs/contributors

3. **Traffic**: Shows visitor statistics (for maintainers)
   - https://github.com/AichaDigital/unblock/graphs/traffic

4. **Community Standards**: Checklist for open source best practices
   - https://github.com/AichaDigital/unblock/community

## 🔐 Deploy Keys & Secrets

### For CI/CD
- Use GitHub Actions secrets for sensitive data
- Never commit secrets to the repository
- Rotate secrets regularly

### For External Services
- Use deploy keys with read-only access when possible
- Limit scope of access tokens
- Set expiration dates for tokens

## 📧 Notifications

Configure notification settings:
1. Go to: https://github.com/settings/notifications
2. Customize what you want to be notified about
3. Consider setting up email filters for GitHub notifications

## 🎯 Project Boards (Optional)

For roadmap and task tracking:
1. Go to: https://github.com/AichaDigital/unblock/projects
2. Create a project board
3. Add automation for issue/PR workflow

## 📝 Checklist for New Public Repository

Before making the repository public:

- [ ] Review all code for sensitive information (passwords, IPs, domains)
- [ ] Ensure `.env.example` exists and is properly configured
- [ ] Add LICENSE file (✅ Already done - AGPL v3.0)
- [ ] Add comprehensive README (✅ Already done)
- [ ] Add CONTRIBUTING.md (✅ Already done)
- [ ] Add CODE_OF_CONDUCT.md (✅ Already done)
- [ ] Add SECURITY.md (✅ Already done)
- [ ] Configure branch protection rules
- [ ] Enable Discussions (✅ Already done)
- [ ] Configure issue templates (✅ Already done)
- [ ] Add PR template (✅ Already done)
- [ ] Set up CI/CD (✅ Already done)
- [ ] Add badges to README (✅ Already done)
- [ ] Test installation process from scratch
- [ ] Review and respond to any security alerts
- [ ] Add topics/tags to repository for discoverability

## 🚀 Making Repository Public

When ready to make the repository public:

1. Go to: https://github.com/AichaDigital/unblock/settings
2. Scroll down to "Danger Zone"
3. Click "Change visibility"
4. Select "Make public"
5. Confirm by typing the repository name

**Note**: This action cannot be easily undone, ensure everything is ready!

## 📚 Resources

- [GitHub Docs - Managing Releases](https://docs.github.com/en/repositories/releasing-projects-on-github)
- [GitHub Docs - Branch Protection](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches)
- [GitHub Docs - Security Advisories](https://docs.github.com/en/code-security/security-advisories)
- [Open Source Guides](https://opensource.guide/)

