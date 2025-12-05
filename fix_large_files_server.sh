#!/bin/bash
# Script untuk fix large files di server
# Jalankan di server: bash fix_large_files_server.sh

echo "=== FIXING LARGE FILES IN GIT ==="
echo ""

cd /home/ymsuperadmin/public_html

# 1. Hapus file besar dari Git tracking
echo "1. Removing large files from Git tracking..."
git rm --cached .git.zip 2>/dev/null && echo "  ✓ Removed .git.zip from Git" || echo "  ⚠ .git.zip not found in Git"
git rm --cached "tms.ymsofterp.com/app.zip" 2>/dev/null && echo "  ✓ Removed tms.ymsofterp.com/app.zip from Git" || echo "  ⚠ tms.ymsofterp.com/app.zip not found in Git"

# 2. Tambahkan ke .gitignore
echo ""
echo "2. Adding to .gitignore..."
if ! grep -q "^\*\.zip$" .gitignore 2>/dev/null; then
    echo "*.zip" >> .gitignore
    echo "  ✓ Added *.zip"
fi

if ! grep -q "^\.git\.zip$" .gitignore 2>/dev/null; then
    echo ".git.zip" >> .gitignore
    echo "  ✓ Added .git.zip"
fi

if ! grep -q "^tms\.ymsofterp\.com/" .gitignore 2>/dev/null; then
    echo "tms.ymsofterp.com/" >> .gitignore
    echo "  ✓ Added tms.ymsofterp.com/"
fi

# 3. Hapus dari Git history (jika sudah ter-commit)
echo ""
echo "3. Removing from Git history (if already committed)..."
echo "   This may take a while..."

# Cek apakah file ada di history
if git log --all --full-history -- ".git.zip" | grep -q .; then
    echo "   Found .git.zip in history, removing..."
    git filter-branch --force --index-filter \
      "git rm --cached --ignore-unmatch .git.zip" \
      --prune-empty --tag-name-filter cat -- --all 2>/dev/null || echo "   Filter-branch failed, trying alternative method..."
fi

if git log --all --full-history -- "tms.ymsofterp.com/app.zip" | grep -q .; then
    echo "   Found tms.ymsofterp.com/app.zip in history, removing..."
    git filter-branch --force --index-filter \
      "git rm --cached --ignore-unmatch 'tms.ymsofterp.com/app.zip'" \
      --prune-empty --tag-name-filter cat -- --all 2>/dev/null || echo "   Filter-branch failed, trying alternative method..."
fi

# 4. Clean up
echo ""
echo "4. Cleaning up..."
git reflog expire --expire=now --all 2>/dev/null
git gc --prune=now 2>/dev/null

# 5. Commit perubahan
echo ""
echo "5. Committing changes..."
git add .gitignore
git commit -m "Remove large zip files from Git and add to .gitignore" || echo "  No changes to commit"

echo ""
echo "=== DONE ==="
echo ""
echo "Next steps:"
echo "1. Check status: git status"
echo "2. Check if large files still in history: git log --all --full-history -- '.git.zip'"
echo "3. If still present, you may need to reset to commit before files were added:"
echo "   git log --oneline --all | head -20"
echo "   git reset --hard <commit-hash-before-files>"
echo "4. Push: git push origin master --force"
echo ""
echo "⚠️  WARNING: Force push will overwrite remote history!"
echo ""

