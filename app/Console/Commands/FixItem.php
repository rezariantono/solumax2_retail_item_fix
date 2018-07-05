<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixItem extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:item {tenantId} {itemCategoryId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicates item on tenant database, based on item category id';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {

        $newDatabaseName = config('database.connections.mysql.database') . '_' . $this->argument('tenantId');
        config(['database.connections.mysql.database' => $newDatabaseName]);

        $max = 100;
        $totalRows = \DB::select('SELECT count(id) as count FROM items items1 where item_category_id = 1 and exists (select 1 from items items2 where items2.code = items1.code limit 1,1)')[0]->count;
        $pages = ceil($totalRows / $max);

        $choice = $this->choice('Repairing ' . (string) $totalRows . ' items. Continue?', ['YES', 'NO']);

        if (($choice == 'NO')) {
            return;
        }

        for ($i = 0; $i < $pages; $i++) {

            $offset = $i * $max;
//            $start = ($offset == 0 ? 0 : ($offset + 1));

            $collections = \DB::select('SELECT * FROM items items1 where item_category_id = 1 and exists (select 1 from items items2 where items2.code = items1.code limit 1,1) ORDER BY price ASC LIMIT ' . $offset . ',' . $max);

            foreach ($collections as $row) {

                $inventoryExists = \DB::table('inventories')->where('item_id', $row->id)->count() >= 1;
                $cleared = \DB::table('items')->where('code', $row->code)->count() == 1;

                if (!$inventoryExists && !$cleared) {
                    \DB::table('items')->where('id', $row->id)->delete();
                    $this->info('Item Deleted. ID: ' . $row->id);
                } else {
                    $this->warn('Item Not Deleted. ID: ' . $row->id . ' => ' . ($inventoryExists ? ' Inv exists ' : '') . ($cleared ? ' Cleared' : ''));
                }
            }
        }
    }

}
