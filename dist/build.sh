#!/bin/bash

# Multi-DB SQL Tool - Docker Build Script

set -e

echo "==================================="
echo "Multi-DB SQL Tool - Docker Build"
echo "==================================="

# プロジェクトルートに移動
cd ..

# 現在のディレクトリをチェック
if [ ! -d "src" ]; then
    echo "Error: src directory not found. Please run this script from the dist directory."
    exit 1
fi

# イメージ名とタグ
IMAGE_NAME="multi-db-sql-tool"
VERSION=${1:-"latest"}

echo "Building Docker image: $IMAGE_NAME:$VERSION"

# ビルド実行（distディレクトリのDockerfileを使用）
docker build -f dist/Dockerfile -t "$IMAGE_NAME:$VERSION" .

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Build successful!"
    echo ""
    echo "Next steps:"
    echo "  1. Run container:"
    echo "     docker run -d -p 8080:80 $IMAGE_NAME:$VERSION"
    echo ""
    echo "  2. Or use Docker Compose:"
    echo "     docker-compose up -d"
    echo ""
    echo "  3. Access application:"
    echo "     http://localhost:8080"
    echo ""

    # イメージ情報を表示
    echo "Image info:"
    docker images "$IMAGE_NAME:$VERSION"
else
    echo "❌ Build failed!"
    exit 1
fi
