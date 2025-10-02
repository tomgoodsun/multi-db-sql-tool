@echo off
REM Multi-DB SQL Tool - Docker Build Script for Windows

echo ===================================
echo Multi-DB SQL Tool - Docker Build
echo ===================================

REM プロジェクトルートに移動
cd ..

REM 現在のディレクトリをチェック
if not exist "src" (
    echo Error: src directory not found. Please run this script from the dist directory.
    exit /b 1
)

REM イメージ名とタグ
set IMAGE_NAME=multi-db-sql-tool
set VERSION=%1
if "%VERSION%"=="" set VERSION=latest

echo Building Docker image: %IMAGE_NAME%:%VERSION%

REM ビルド実行（distディレクトリのDockerfileを使用）
docker build -f dist/Dockerfile -t "%IMAGE_NAME%:%VERSION%" .

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✅ Build successful!
    echo.
    echo Next steps:
    echo   1. Run container:
    echo      docker run -d -p 8080:80 %IMAGE_NAME%:%VERSION%
    echo.
    echo   2. Or use Docker Compose:
    echo      docker-compose up -d
    echo.
    echo   3. Access application:
    echo      http://localhost:8080
    echo.
    
    REM イメージ情報を表示
    echo Image info:
    docker images "%IMAGE_NAME%:%VERSION%"
) else (
    echo ❌ Build failed!
    exit /b 1
)

pause
