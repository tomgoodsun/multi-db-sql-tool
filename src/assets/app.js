(function(window, document) {

  let editorElement = document.getElementById("sql-editor")
  let sqlEditor = null;

  let initCodeMirror = () => {
    // Initialize CodeMirror
    sqlEditor = CodeMirror(editorElement, {
      mode: 'text/x-mysql',
      theme: 'eclipse',
      lineNumbers: true,
      indentUnit: 2,
      smartIndent: true,
      lineWrapping: false,
      //viewportMargin: Infinity,
      extraKeys: {
        "Ctrl-Enter": () => {
          //this.executeQuery();
        },
        "Ctrl-Space": "autocomplete"
      },
      value: ''
    });
    sqlEditor.setSize('100%', '100%');
  };

  initCodeMirror();

  let adjustStyles = () => {
    document.querySelectorAll('.table-list').forEach(el => {
      el.style.height = (window.innerHeight - el.offsetTop) + 'px';
    });
    setTimeout(adjustStyles, 100);
  };
  adjustStyles();

})(window, window.document);
