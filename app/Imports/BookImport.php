<?php

namespace App\Imports;

use App\Models\Book;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class BookImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Book([
            'tahun_terbit' => $row['tahun_terbit'],
            'isbn' => $row['isbn'],
            'kategori' => $row['kategori'],
            'title' => $row['title'],
            'creators' => $row['author'],
            'tempat_terbit' => $row['tempat_terbit'],
            'penerbit' => $row['penerbit'],
            'page' => $row['page']
        ]);
    }

    // public function headingRow(): int
    // {
    //     return 5;
    // }

    public function rules(): array
    {
        return [
            'tahun_terbit' => 'required|max:4',
            'isbn' => 'required|max:50',
            'kategori' => 'required|max:50',
            'title' => 'required|max:255|unique:books,title',
            'author' => 'nullable|max:255',
            'tempat_terbit' => 'required|max:100',
            'penerbit' => 'required|max:255',
            'page' => 'required|max:20'
        ];
    }
}
