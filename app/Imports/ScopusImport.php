<?php

namespace App\Imports;

use App\Models\Publication;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ScopusImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $quartile = $row['quartile'] !== '-' ? $row['quartile'] : 'Jurnal Nasional';

        return new Publication([
            'identifier' => $row['identifier'],
            'quartile' => $quartile,
            'title' => $row['title'],
            'publication_name' => $row['publication_name'],
            'creators' => $row['creator'],
            'year' => $row['year'],
            'citation' => $row['citation'],
            'category' => 'scopus',
        ]);
    }

    // public function headingRow(): int
    // {
    //     return 5;
    // }

    public function rules(): array
    {
        return [
            'title' => 'required',
        ];
    }
}
