#!/bin/bash

# Exit immediately if any command fails
set -e

echo "🔄 Step 1: Pulling latest changes from GitHub..."
git pull origin main --rebase

echo "➕ Step 2: Staging files..."
git add .

# Check if there are actually changes to commit
if git diff-index --quiet HEAD --; then
    echo "⚠️ No new files or changes detected to upload."
    exit 0
fi

# Ask you for a custom commit message, defaults to 'Update modules' if you press Enter
echo "💬 Enter a commit message (or press Enter for default):"
read -r commit_msg
if [ -z "$commit_msg" ]; then
    commit_msg="Update modules via automation script"
fi

echo "💾 Step 3: Committing changes..."
git commit -m "$commit_msg"

echo "🚀 Step 4: Pushing to GitHub..."
git push origin main

echo "✅ Success! Your files have been uploaded."

