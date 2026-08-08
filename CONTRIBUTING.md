# Contributing to Aurora Admin

Thank you for helping improve Aurora Admin.

## Before opening an issue

- Search existing issues first.
- Confirm the issue still occurs with the latest release.
- Disable unrelated plugins where practical to identify conflicts.
- Never include passwords, API keys, private URLs, or customer data.

## Bug reports

Include:

- Aurora Admin version
- WordPress and PHP versions
- Browser and operating system
- Clear reproduction steps
- Expected and actual behavior
- Screenshots or console errors when useful

## Development

```bash
cd app
npm install
npm run dev
```

Production assets are built with:

```bash
npm run build
```

Keep changes focused. PHP should follow WordPress security practices, including capability checks, nonce verification, validation, sanitization, and escaping. Vue changes should reuse existing shared components and preserve keyboard and mobile usability.

## Pull requests

1. Create a branch from the default branch.
2. Make one logical change per pull request.
3. Test affected screens in both light and dark mode.
4. Update documentation and `CHANGELOG.md` when behavior changes.
5. Explain the reason for the change and how it was tested.

By contributing, you agree that your work is licensed under GPL-2.0-or-later.
