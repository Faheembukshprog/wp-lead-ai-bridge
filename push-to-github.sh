#!/usr/bin/env bash
# push-to-github.sh
# -----------------------------------------------------------------------------
# Push the WP Lead AI Bridge plugin to a NEW private GitHub repo on your
# own account. No credentials are read from disk and nothing is sent to
# any third party. The script uses your locally installed `gh` CLI which
# authenticates you directly with GitHub via browser.
#
# USAGE:
#   cd wp-lead-ai-bridge-bundle
#   bash push-to-github.sh
#
# OR with custom repo name:
#   REPO_NAME=my-wp-plugin bash push-to-github.sh
#
# PREREQUISITES:
#   - GitHub CLI installed:   https://cli.github.com/
#     macOS:   brew install gh
#     Ubuntu:  see official deb install
#     Windows: winget install GitHub.cli
#   - You are logged into gh:
#     gh auth login
#     (choose GitHub.com, HTTPS, login via web browser)
#
# WHAT THIS SCRIPT DOES:
#   1. Verifies you are inside the plugin folder
#   2. Verifies gh is installed and authenticated
#   3. Creates a private GitHub repo (name from $REPO_NAME or default)
#   4. Sets the local origin to the new repo
#   5. Pushes the main branch with the initial commit
#   6. Prints the repo URL so you can copy it into the CV
# -----------------------------------------------------------------------------

set -euo pipefail

# Colors for clearer output
if [[ -t 1 ]]; then
  GREEN=$'\033[1;32m'; BLUE=$'\033[1;34m'; RED=$'\033[1;31m'; RESET=$'\033[0m'
else
  GREEN=''; BLUE=''; RED=''; RESET=''
fi

echo "${BLUE}== WP Lead AI Bridge → GitHub Push ==${RESET}"
echo ""

# --- Step 1: verify we are in the right folder -------------------------------
if [[ ! -f wp-lead-ai-bridge.php ]] || [[ ! -d .git ]]; then
  echo "${RED}ERROR:${RESET} Run this script from inside the unpacked plugin folder"
  echo "       (the folder that contains wp-lead-ai-bridge.php and .git/)."
  echo "       Current dir: $(pwd)"
  exit 1
fi
echo "  [1/5] Plugin folder OK: $(pwd)"

# --- Step 2: verify gh is installed and authenticated -----------------------
if ! command -v gh >/dev/null 2>&1; then
  echo "${RED}ERROR:${RESET} GitHub CLI (gh) is not installed."
  echo "       Install it: https://cli.github.com/"
  echo "       Then run: gh auth login"
  echo "       Then rerun this script."
  exit 1
fi
echo "  [2/5] gh CLI installed: $(gh --version | head -1)"

if ! gh auth status >/dev/null 2>&1; then
  echo "${RED}ERROR:${RESET} You are not logged into GitHub CLI."
  echo "       Run: gh auth login"
  echo "       Pick: GitHub.com → HTTPS → Login with a web browser."
  echo "       Then rerun this script."
  exit 1
fi
GH_USER=$(gh api user --jq .login 2>/dev/null || echo "")
if [[ -z "$GH_USER" ]]; then
  echo "${RED}ERROR:${RESET} Could not read your GitHub username."
  echo "       Run: gh auth login"
  exit 1
fi
echo "        Authenticated as: ${GREEN}${GH_USER}${RESET}"

# --- Step 3: repo name + description ----------------------------------------
REPO_NAME="${REPO_NAME:-wp-lead-ai-bridge}"
REPO_DESC="WordPress plugin: captures Contact Form 7 / WPForms submissions, logs to a custom MySQL table, and forwards the JSON payload to an n8n webhook for AI-driven classification and routing."

echo "  [3/5] Repo will be created:"
echo "        ${BLUE}github.com/${GH_USER}/${REPO_NAME}${RESET} (private)"

# Check if the repo already exists
if gh repo view "${GH_USER}/${REPO_NAME}" >/dev/null 2>&1; then
  echo "${RED}ERROR:${RESET} A repo named '${REPO_NAME}' already exists on your account."
  echo "       Pick a different name:"
  echo "         REPO_NAME=wp-lead-ai-bridge-v2 bash push-to-github.sh"
  echo "       Or delete the existing one first:"
  echo "         gh repo delete ${GH_USER}/${REPO_NAME} --yes"
  exit 1
fi

# --- Step 4: create the private repo and set remote --------------------------
echo "  [4/5] Creating private repo and pushing..."

# Set the local git origin to the new repo
REMOTE_URL="https://github.com/${GH_USER}/${REPO_NAME}.git"

# Create the repo via gh, then push the existing local commit
gh repo create "${GH_USER}/${REPO_NAME}" \
  --private \
  --description "${REPO_DESC}" \
  --source=. \
  --remote=origin \
  --push

# --- Step 5: confirm + print link -------------------------------------------
echo ""
echo "${GREEN}== Done! ==${RESET}"
echo ""
echo "  Repo URL:  ${BLUE}https://github.com/${GH_USER}/${REPO_NAME}${RESET}"
echo "  Clone URL: ${REMOTE_URL}"
echo "  Visibility: private"
echo "  Branch:     main"
echo "  Commit:     $(git rev-parse --short HEAD)"
echo ""
echo "  Next steps:"
echo "    1. Visit the repo URL above and confirm it loaded."
echo "    2. Update your CV with this GitHub URL (currently points to"
echo "       github.com/Faheembukshprog — change to github.com/${GH_USER}/${REPO_NAME})."
echo "    3. Add 3 screenshots to screenshots/ folder (lead-log.png,"
echo "       n8n-workflow.png, sheet-output.png) — see screenshots/PLACEHOLDER.txt"
echo "    4. Commit + push:"
echo "       git add screenshots/*.png && git commit -m \"Add live demo screenshots\" && git push"
echo ""
