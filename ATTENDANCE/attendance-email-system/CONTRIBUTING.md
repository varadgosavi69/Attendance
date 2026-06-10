# Contributing

Thanks for your interest in this project. This is a small, phased
modernization effort, so the workflow is intentionally simple.

## Workflow

1. **Fork** the repository to your own GitHub account.
2. **Clone** your fork and create a branch off `main`:
   ```bash
   git checkout -b fix/short-description
   ```
3. **Make your changes.** Keep commits focused — one logical change per
   commit, with a message that explains *why*, not just *what*.
4. **Run the relevant test suite(s)** before opening a PR:
   ```bash
   # Laravel API
   docker-compose exec api php artisan test

   # ML service
   docker-compose exec ml-service pytest -v --cov=app --cov-report=term
   ```
5. **Open a Pull Request** against `main` on the upstream repository.
   Describe what changed and why, and link any related issue.

## Guidelines

- Don't commit `.env` files, credentials, or other secrets — use
  `.env.example` to document new configuration variables.
- If you fix a bug uncovered by a test, note it in the PR description.
- New features should include or update tests where practical.
- Known pre-existing test failures are tracked in
  [`docs/known-issues.md`](docs/known-issues.md) — please don't bundle fixes
  for those into unrelated PRs.
