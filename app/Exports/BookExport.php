<?php

namespace App\Exports;

use App\Models\Book;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Book::all();
    }

    public function headings(): array
    {
        return [
            '#',
            'Tahun Terbit',
            'ISBN',
            'Kategori',
            'Title',
            'Creators',
            'Tempat Terbit',
            'Penerbit',
            'Page'
        ];
    }

    public function map($book): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $book->tahun_terbit,
            $book->isbn,
            $book->kategori,
            $book->title,
            $book->creators,
            $book->tempat_terbit,
            $book->penerbit,
            $book->page,
        ];
    }
}
