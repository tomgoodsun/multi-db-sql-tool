<?php
namespace MultiDbSqlTool;

class WebHandler
{
    protected $action = '';

    public function __construct()
    {
        $this->action = $_REQUEST['action'] ?? '';
    }

    protected function json(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Api
     *
     * @return void
     */
    protected function processApiQuery()
    {
        $data = [];
        $sql = $_REQUEST['sql'] ?? '';

        $sqls = Utility::splitSqlStatements($sql);

        foreach ($sqls as $sql) {
            $query = new Query($sql);

            $data[] = $query->query();
        }

        $this->json($data);
    }

    /**
     * Normal web request handler
     *
     * @return void
     */
    protected function processWeb()
    {
        require_once __DIR__ . '/../assets/template/index.inc.html';
    }


    public function execute()
    {
        switch ($this->action) {
            case 'api_query':
                $this->processApiQuery();
                break;
            default:
                $this->processWeb();
                break;
        }
    }

    public function handleRequest($request) {
        // Handle incoming web requests
    }
}
