<?php

namespace App\Console\Commands;

use App\Facades\Action;
use App\Models\Transaction;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use function Laravel\Prompts\select;

class ImportTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports Transactions';

    protected string $filesDir = 'app/private';

    protected string $fileName = '';

    protected array $data = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->selectFile();

        $this->loadFile();

        $this->saveTransactions();
    }

    protected function selectFile()
    {
        $fileOpts = $this->getFileOptions();

        $file = select(
            label: 'Please choose a file to process',
            options: $fileOpts,
        );

        $this->fileName = $fileOpts[$file];
    }

    protected function getFileOptions(): array
    {
        $path = storage_path($this->filesDir);
        $files = scandir($path);
        return array_filter($files, fn ($f) => substr($f, -4) === '.CSV');
    }

    protected function loadFile()
    {
        $path = storage_path($this->filesDir . DIRECTORY_SEPARATOR . $this->fileName);
        $csv = file_get_contents($path);

        $headers = null;

        foreach (explode(PHP_EOL, $csv) as $i => $rowStr) {
            if (empty($rowStr)) {
                continue;
            }

            $line = str_getcsv($rowStr);

            if ($i == 0) {
                $headers = $line;
                continue;
            }

            $this->data[] = $this->translateRow($headers, $line);
        }
    }

    protected function translateRow(array $headers, array $line): array
    {
        $row = array_combine($headers, $line);

        $hash = md5(implode('', [
            $row['Transaction Date'],
            $row['Description'],
            $row['Amount'],
        ]));

        $vendor = Vendor::firstOrCreate([
            'name' => $row['Description'],
        ]);

        return [
            'vendor_id' => $vendor->id,
            'hash' => $hash,
            'transaction_date' => Carbon::parse($row['Transaction Date']),
            'post_date' => Carbon::parse($row['Post Date']),
            'category' => $row['Category'],
            'type' => $row['Type'],
            'amount' => floatval($row['Amount']),
            'memo' => $row['Memo'],
        ];
    }

    protected function saveTransactions()
    {
        foreach ($this->data as $item) {
            Action::createModel(Transaction::class, $item);
        }
    }
}
