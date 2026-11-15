<?php

namespace App\Http\Controllers\Exports;

use App\Documents\Payslip;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Pdf\PdfRenderer;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A payslip as a PDF, made on demand from stored data. Same permission as the
 * salary pages: the salary permission and access to that employee.
 */
class PayslipController extends Controller
{
    public function __invoke(Request $request, Employee $employee, Payslip $payslip, PdfRenderer $pdf): Response
    {
        $this->authorize('viewSalary', $employee);

        $month = Dates::month($request->query('month'));

        $employee->loadMissing('office:id,name');
        $data = $payslip->build($employee, $month);

        abort_if($data === null, 404, __('This employee has no salary for that month.'));

        return $pdf->response(
            'pdf.payslip',
            $data,
            'Payslip',
            'khazna-payslip-'.PdfRenderer::slug($employee->name, 'employee-'.$employee->id).'-'.$month->format('Y-m').'.pdf',
        );
    }
}
