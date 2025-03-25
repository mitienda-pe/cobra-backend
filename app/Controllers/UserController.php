<?php

namespace App\Controllers;

use App\Models\UserModel;

class UserController extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index()
    {
        return view('users/index', [
            'title' => 'Users',
            'users' => $this->userModel->findAll()
        ]);
    }

    public function create()
    {
        return view('users/create', [
            'title' => 'Create User'
        ]);
    }

    public function store()
    {
        if (!$this->validate([
            'name' => 'required|min_length[3]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]'
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->userModel->insert([
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT)
        ]);

        return redirect()->to('/users')->with('message', 'User created successfully');
    }

    public function edit($id)
    {
        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/users')->with('error', 'User not found');
        }

        return view('users/edit', [
            'title' => 'Edit User',
            'user' => $user
        ]);
    }

    public function update($id)
    {
        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/users')->with('error', 'User not found');
        }

        $rules = [
            'name' => 'required|min_length[3]',
            'email' => 'required|valid_email'
        ];

        // Only validate email uniqueness if it changed
        if ($user['email'] !== $this->request->getPost('email')) {
            $rules['email'] .= '|is_unique[users.email]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email')
        ];

        // Only update password if provided
        if ($password = $this->request->getPost('password')) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->userModel->update($id, $data);

        return redirect()->to('/users')->with('message', 'User updated successfully');
    }

    public function delete($id)
    {
        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/users')->with('error', 'User not found');
        }

        $this->userModel->delete($id);

        return redirect()->to('/users')->with('message', 'User deleted successfully');
    }
}
