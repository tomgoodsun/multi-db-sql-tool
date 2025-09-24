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

// JavaScript Dependencies - グローバル変数として公開
import jQuery from 'jquery';
import * as bootstrap from 'bootstrap';
import CodeMirror from 'codemirror';
import 'codemirror/mode/sql/sql';
import { createGrid } from 'ag-grid-community';
import * as XLSX from 'xlsx';
import { format } from 'sql-formatter';

// グローバル変数として設定
window.$ = window.jQuery = jQuery;
window.bootstrap = bootstrap;
window.CodeMirror = CodeMirror;
window.agGrid = { createGrid };
window.XLSX = XLSX;
window.sqlFormatter = { format };

console.log('Multi-DB SQL Tool: Vendor libraries loaded');
