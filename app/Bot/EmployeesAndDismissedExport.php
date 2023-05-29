<?php

namespace App\Bot;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesAndDismissedExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new class implements FromCollection, WithTitle, WithHeadings  {
            public function collection()
            {
                return DB::table('list_employees')->get();
            }

            public function title(): string
            {
                return 'Сотрудники в штате';
            }
            
            public function headings(): array
            {
                return ["ID", "ФИО", "Должность", "Телефон", "Дата рождения", "Дата приема"];
            }
        };

        $sheets[] = new class implements FromCollection, WithTitle, WithHeadings  {
            public function collection()
            {
                return DB::table('list_dismissed')->get();
            }

            public function title(): string
            {
                return 'Уволенные';
            }
            
            public function headings(): array
            {
                return ["ID", "ФИО", "Должность", "Телефон", "Дата рождения", "Дата увольнения"];
            }
        };

        return $sheets;
    }
}