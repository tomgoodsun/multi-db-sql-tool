/**
 * Vendor Entry Point
 * CDNから読み込んでいた依存関係をバンドルする
 */

// CSS Dependencies
import 'normalize.css/normalize.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'codemirror/lib/codemirror.css';
import 'codemirror/theme/eclipse.css';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';

// JavaScript Dependencies
import jQuery from 'jquery';
import * as bootstrap from 'bootstrap';
import CodeMirror from 'codemirror';
import 'codemirror/mode/sql/sql';
import { createGrid } from 'ag-grid-community';
import * as XLSX from 'xlsx';
import { format } from 'sql-formatter';

// グローバル変数として設定（既存のコードとの互換性のため）
window.$ = jQuery;
window.jQuery = jQuery;
window.bootstrap = bootstrap;
window.CodeMirror = CodeMirror;
window.agGrid = { createGrid };
window.XLSX = XLSX;
window.sqlFormatter = { format };

// デバッグ用：グローバル変数が正しく設定されたか確認
console.log('Multi-DB SQL Tool: Vendor libraries loaded');
console.log('jQuery:', typeof window.jQuery !== 'undefined' ? '✓' : '✗');
console.log('bootstrap:', typeof window.bootstrap !== 'undefined' ? '✓' : '✗');
console.log('CodeMirror:', typeof window.CodeMirror !== 'undefined' ? '✓' : '✗');
console.log('agGrid:', typeof window.agGrid !== 'undefined' ? '✓' : '✗');
console.log('XLSX:', typeof window.XLSX !== 'undefined' ? '✓' : '✗');
console.log('sqlFormatter:', typeof window.sqlFormatter !== 'undefined' ? '✓' : '✗');
