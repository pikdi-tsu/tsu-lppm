<?php

namespace App\Imports;

use App\Models\Publication;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class GoogleImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $accreditation = $row['accreditation'] !== '-' ? $row['accreditation'] : 'Jurnal Nasional';

        return new Publication([
            'accreditation' => $accreditation,
            'title' => $row['title'],
            'journal' => $row['journal'],
            'creators' => $row['authors'],
            'year' => $row['year'],
            'citation' => $row['citation'],
            'category' => 'google',
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
