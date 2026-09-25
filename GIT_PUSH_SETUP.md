# GitHub push setup for SJ-cottage-food

## 1) Create the remote repository

Go to GitHub and create a new repository named `SJ-cottage-food`.

Then choose one of these connection methods:

### HTTPS

```bash
git branch -M main
git remote add origin https://github.com/YOUR_GITHUB_USERNAME/SJ-cottage-food.git
git push -u origin main
```

### SSH

```bash
git branch -M main
git remote add origin git@github.com:YOUR_GITHUB_USERNAME/SJ-cottage-food.git
git push -u origin main
```

## 2) If the repo is not initialized yet

```bash
git init
git add .
git commit -m "Initial project commit"
git branch -M main
git remote add origin https://github.com/YOUR_GITHUB_USERNAME/SJ-cottage-food.git
git push -u origin main
```

## 3) If you already have a repo and need to reconnect

```bash
git remote set-url origin https://github.com/YOUR_GITHUB_USERNAME/SJ-cottage-food.git
git add .
git commit -m "Prepare repository for GitHub push"
git push -u origin main
```

## 4) Safe before push checklist

- [ ] Confirm no secrets or API keys are committed
- [ ] `.env` and local configuration files are excluded
- [ ] Local database files are not tracked
- [ ] `node_modules` is not included
- [ ] The app builds successfully
- [ ] `php build.php` runs without errors
- [ ] Generated static files in `public/` are intended to be part of the repo
- [ ] `git status` shows only the intended files

## 5) Quick verification commands

```bash
git status
php build.php
```

If everything looks clean:

```bash
git add .
git commit -m "Initial project commit"
git push -u origin main
```
