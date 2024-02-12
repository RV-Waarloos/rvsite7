<?php

namespace App\Actions;

use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;
use League\Csv\Reader;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;
use Illuminate\Support\Str;

class ProcessClubmemberCsv extends Action
{
    protected static $title = 'Wow wow';

    /**
     * The run method
     *
     * @return void
     */
    public function run($items, $values)
    {
        foreach ($items as $key => $value) {
            $xx = $value;


            $path = Storage::disk($value->container->disk)->path($value->path);

            $reader = Reader::createFromPath($path, 'r');
            $reader->setDelimiter(';');
            $reader->setHeaderOffset(0);
            $records = $reader->getRecords();
            foreach ($records as $offset => $record) {
                $xx = $record;

                $clubmember = Entry::query()->where('collection', 'clubmembers')->where('email', '=' ,$record['EMAIL'])->first();
                if ($clubmember) {
                    $xx = $clubmember;
                } else {
                    $entry = Entry::make()->collection('clubmembers')->slug((string) Str::uuid());
                    $entry
                        ->data([
                            'first_name' => $record['VOORNAAM'],
                            'last_name' => $record['NAAM'],
                            'email' => $record['EMAIL'],
                            'birthdate' => $record['GEBDAT'],
                            'straat' => $record['STRAAT_NR'],
                            'postcode' => $record['POSTCODE'],
                            'gemeente' => $record['GEMEENTE'],
                        ]);
                    $entry->save();
                }
                //$offset : represents the record offset
                //var_export($record) returns something like
                // array(
                //  'First Name' => 'jane',
                //  'Last Name' => 'jane',
                //  'E-mail' => null
                // );
            }
        }

        return __('The thing was done.');
    }

    public function visibleTo($item)
    {
        if ($item instanceof Asset) {
            return $item->container->handle === 'clubmember_csv';
        }
        return false;
    }
}
