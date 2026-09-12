# WP Lead AI Bridge — GitHub Push Bundle

This bundle contains everything you need to push the **WP Lead AI Bridge**
plugin to a private GitHub repo from your own machine. No credentials are
embedded and nothing is sent to any third party.

## What's inside

```
wp-lead-ai-bridge/
├── .git/                              ← full local git repo (1 commit on main)
├── .gitignore                         ← WordPress-safe ignore rules
├── LICENSE                            ← GPL v2 or later
├── README.md                          ← architecture + stack overview
├── SETUP.md                           ← full local-to-live setup walkthrough
├── push-to-github.sh                  ← one-command push helper script
├── wp-lead-ai-bridge.php              ← main plugin file (354 lines)
├── includes/
│   └── class-lead-log-list-table.php  ← native wp-admin log viewer
├── n8n-workflow/
│   └── lead-ai-pipeline.json          ← importable n8n workflow
└── screenshots/
    └── PLACEHOLDER.txt                ← replace with real PNGs later
```

## Quick start (3 steps)

### 1. Unzip the bundle

```bash
unzip wp-lead-ai-bridge-push-bundle.zip
cd wp-lead-ai-bridge
```

### 2. Install the GitHub CLI (if you don't already have it)

| OS | Command |
|---|---|
| macOS (Homebrew) | `brew install gh` |
| Ubuntu / Debian | `sudo apt install gh` (or see https://github.com/cli/cli#installation) |
| Windows (winget) | `winget install GitHub.cli` |
| Windows (scoop) | `scoop install gh` |

Then log in once:

```bash
gh auth login
```

Choose:
- **GitHub.com**
- **HTTPS**
- **Authenticate Git with your GitHub credentials? Yes**
- **Login with a web browser** → opens a browser, you authorize, done.

### 3. Run the push script

```bash
bash push-to-github.sh
```

That's it. The script will:

1. Verify you're in the right folder and `gh` is logged in
2. Read your GitHub username automatically
3. Create a new **private** repo named `wp-lead-ai-bridge` on your account
4. Push the initial commit (commit `985bb7e`, 8 files, 835 lines)
5. Print the new repo URL so you can paste it into your CV

## Customizing the repo name

If `wp-lead-ai-bridge` is taken (or you prefer a different name):

```bash
REPO_NAME=wp-lead-ai-bridge-v2 bash push-to-github.sh
```

## After the push

The script prints your repo URL — e.g. `https://github.com/YOUR-USERNAME/wp-lead-ai-bridge`.
That's the link your CV points to. Update the CV if your username differs
from `Faheembukshprog`.

Then over the next few days:

1. **Add three screenshots** to `screenshots/` (see `screenshots/PLACEHOLDER.txt`
   for filenames and recommended content). Commit + push:

   ```bash
   git add screenshots/*.png
   git commit -m "Add live demo screenshots"
   git push
   ```

2. **Record the Loom walkthrough** the README reserves a slot for —
   90 seconds of "submit form → switch to n8n → switch to Google Sheet"
   is worth more than any bullet point on the CV. Embed it in the README.

3. **Tag a release** so the repo looks shipped, not work-in-progress:

   ```bash
   git tag -a v1.0.0 -m "WP Lead AI Bridge v1.0.0 — initial public release"
   git push origin v1.0.0
   gh release create v1.0.0 --generate-notes
   ```

## Troubleshooting

| Error | Fix |
|---|---|
| `gh: command not found` | Install GitHub CLI from https://cli.github.com/ |
| `gh auth status` fails | Run `gh auth login` and pick the web browser flow |
| `gh repo create` says repo exists | Use `REPO_NAME=something-else bash push-to-github.sh` |
| Push fails with "refusing to allow an OAuth App to create or update workflow" | Your token lacks the `workflow` scope. Run `gh auth refresh -h github.com -s workflow` |
| You're on Windows and `bash` isn't available | Use Git Bash (comes with Git for Windows) or WSL |

## Verifying the push worked

After the script finishes, visit the URL it printed. You should see:

- The repo name `wp-lead-ai-bridge` (private badge visible)
- 1 commit: `Initial release: WP Lead AI Bridge v1.0.0`
- Files listed in the repo root: `wp-lead-ai-bridge.php`, `README.md`,
  `SETUP.md`, `includes/`, `n8n-workflow/`, `screenshots/`, `LICENSE`,
  `.gitignore`
- The README renders properly with the architecture diagram

If any of those are missing, the push didn't complete — re-run the script
or push manually with `git push -u origin main`.

---

Built and bundled locally at `/home/z/my-project/upload/wp-lead-ai-bridge/`.
No external services were contacted during preparation of this bundle.
