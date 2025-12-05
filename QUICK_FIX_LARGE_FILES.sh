#!/bin/bash
# Script cepat untuk hapus file besar dari Git - SOLUSI PASTI
# Jalankan di server: bash QUICK_FIX_LARGE_FILES.sh

echo "=== QUICK FIX: REMOVE LARGE FILES FROM GIT ==="
echo ""

cd /home/ymsuperadmin/public_html

# Opsi 1: Hapus .git dan buat baru (PALING MUDAH - Hapus semua history)
echo "Opsi 1: Buat repository baru (hapus semua history)"
echo "Ini akan menghapus semua Git history dan mulai dari awal"
echo ""
read -p "Lanjutkan? (y/n): " confirm

if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
    echo ""
    echo "1. Menghapus .git folder..."
    rm -rf .git
    
    echo "2. Init Git baru..."
    git init
    
    echo "3. Pastikan .gitignore sudah benar..."
    if ! grep -q "^\*\.zip$" .gitignore 2>/dev/null; then
        echo "*.zip" >> .gitignore
    fi
    if ! grep -q "^\.git\.zip$" .gitignore 2>/dev/null; then
        echo ".git.zip" >> .gitignore
    fi
    if ! grep -q "^tms\.ymsofterp\.com/" .gitignore 2>/dev/null; then
        echo "tms.ymsofterp.com/" >> .gitignore
    fi
    
    echo "4. Add semua file (kecuali yang di .gitignore)..."
    git add .
    
    echo "5. Commit..."
    git commit -m "Initial commit - server restore version (large files excluded)"
    
    echo "6. Add remote..."
    git remote add origin https://github.com/khaleedemyr/ymsofterp-vue.git 2>/dev/null || \
    git remote set-url origin https://github.com/khaleedemyr/ymsofterp-vue.git
    
    echo ""
    echo "=== DONE ==="
    echo ""
    echo "Sekarang push dengan:"
    echo "  git push origin master --force"
    echo ""
    exit 0
fi

# Opsi 2: Hapus file besar dari history dengan filter-branch
echo ""
echo "Opsi 2: Hapus file besar dari history (jaga history)"
echo "Ini akan memakan waktu lebih lama tapi history tetap ada"
echo ""
read -p "Lanjutkan? (y/n): " confirm2

if [ "$confirm2" = "y" ] || [ "$confirm2" = "Y" ]; then
    echo ""
    echo "1. Hapus file besar dari Git tracking..."
    git rm --cached .git.zip 2>/dev/null
    git rm --cached "tms.ymsofterp.com/app.zip" 2>/dev/null
    
    echo "2. Pastikan .gitignore sudah benar..."
    if ! grep -q "^\*\.zip$" .gitignore 2>/dev/null; then
        echo "*.zip" >> .gitignore
    fi
    if ! grep -q "^\.git\.zip$" .gitignore 2>/dev/null; then
        echo ".git.zip" >> .gitignore
    fi
    if ! grep -q "^tms\.ymsofterp\.com/" .gitignore 2>/dev/null; then
        echo "tms.ymsofterp.com/" >> .gitignore
    fi
    
    echo "3. Hapus dari semua commit di history..."
    echo "   (Ini mungkin memakan waktu beberapa menit...)"
    
    git filter-branch --force --index-filter \
      "git rm --cached --ignore-unmatch .git.zip 'tms.ymsofterp.com/app.zip'" \
      --prune-empty --tag-name-filter cat -- --all
    
    echo "4. Clean up..."
    git reflog expire --expire=now --all
    git gc --prune=now --aggressive
    
    echo "5. Commit perubahan .gitignore..."
    git add .gitignore
    git commit -m "Remove large files from Git history" || echo "No changes"
    
    echo ""
    echo "=== DONE ==="
    echo ""
    echo "Sekarang push dengan:"
    echo "  git push origin master --force"
    echo ""
    exit 0
fi

echo ""
echo "Dibatalkan."
echo ""

