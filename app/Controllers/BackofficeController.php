<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class BackofficeController extends Controller
{
    use ResponseTrait;

    protected $ligoModel;
    protected $organizationModel;

    public function __construct()
    {
        $this->ligoModel = new \App\Models\LigoModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
        helper(['form', 'url']);
    }

    public function index()
    {
        $data = [
            'title' => 'Backoffice Ligo',
            'breadcrumb' => 'Backoffice'
        ];

        return view('backoffice/index', $data);
    }

    public function balance()
    {
        $data = [
            'title' => 'Balance de Cuenta - Ligo',
            'breadcrumb' => 'Balance de Cuenta'
        ];

        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            $debtorCCI = $this->request->getPost('debtorCCI');
            
            if (empty($debtorCCI)) {
                return $this->fail('El CCI del deudor es requerido', 400);
            }

            $response = $this->ligoModel->getAccountBalance($debtorCCI);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/balance', $data);
    }

    public function transactions()
    {
        $data = [
            'title' => 'Transacciones - Ligo',
            'breadcrumb' => 'Transacciones'
        ];

        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            $requestData = [
                'page' => $this->request->getPost('page') ?: 1,
                'startDate' => $this->request->getPost('startDate'),
                'endDate' => $this->request->getPost('endDate'),
                'debtorCCI' => $this->request->getPost('debtorCCI'),
                'creditorCCI' => $this->request->getPost('creditorCCI')
            ];

            if (empty($requestData['startDate']) || empty($requestData['endDate'])) {
                return $this->fail('Las fechas de inicio y fin son requeridas', 400);
            }

            $response = $this->ligoModel->listTransactions($requestData);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/transactions', $data);
    }

    public function transactionDetail($id)
    {
        $data = [
            'title' => 'Detalle de Transacción - Ligo',
            'breadcrumb' => 'Detalle de Transacción',
            'transaction_id' => $id
        ];

        if ($this->request->isAJAX()) {
            $response = $this->ligoModel->getTransactionDetail($id);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/transaction_detail', $data);
    }

    public function recharges()
    {
        $data = [
            'title' => 'Recargas - Ligo',
            'breadcrumb' => 'Recargas'
        ];

        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            $requestData = [
                'page' => $this->request->getPost('page') ?: 1,
                'startDate' => $this->request->getPost('startDate'),
                'endDate' => $this->request->getPost('endDate')
            ];

            if (empty($requestData['startDate']) || empty($requestData['endDate'])) {
                return $this->fail('Las fechas de inicio y fin son requeridas', 400);
            }

            $response = $this->ligoModel->listRecharges($requestData);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/recharges', $data);
    }

    public function transfer()
    {
        $data = [
            'title' => 'Transferencia Ordinaria - Ligo',
            'breadcrumb' => 'Transferencia Ordinaria'
        ];

        if ($this->request->getMethod() === 'post') {
            $transferData = [
                'debtorParticipantCode' => $this->request->getPost('debtorParticipantCode'),
                'creditorParticipantCode' => $this->request->getPost('creditorParticipantCode'),
                'debtorName' => $this->request->getPost('debtorName'),
                'debtorId' => $this->request->getPost('debtorId'),
                'debtorIdCode' => $this->request->getPost('debtorIdCode'),
                'debtorAddressLine' => $this->request->getPost('debtorAddressLine'),
                'debtorMobileNumber' => $this->request->getPost('debtorMobileNumber'),
                'creditorCCI' => $this->request->getPost('creditorCCI'),
                'amount' => $this->request->getPost('amount'),
                'currency' => $this->request->getPost('currency') ?: 'PEN',
                'unstructuredInformation' => $this->request->getPost('unstructuredInformation')
            ];

            if ($this->request->isAJAX()) {
                $response = $this->ligoModel->processOrdinaryTransfer($transferData);
                
                if (isset($response['error'])) {
                    return $this->fail($response['error'], 400);
                }

                return $this->respond($response);
            } else {
                $response = $this->ligoModel->processOrdinaryTransfer($transferData);
                
                if (isset($response['error'])) {
                    return redirect()->back()->with('error', $response['error']);
                }

                return redirect()->back()->with('success', 'Transferencia procesada exitosamente');
            }
        }

        return view('backoffice/transfer', $data);
    }

    public function transferStatus($transferId)
    {
        if ($this->request->isAJAX()) {
            $response = $this->ligoModel->getTransferStatus($transferId);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return $this->fail('Invalid request', 400);
    }
}