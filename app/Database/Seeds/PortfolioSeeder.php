<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    public function run()
    {
        // Get the organization ID
        $orgId = $this->db->table('organizations')->select('id')->get()->getRow()->id;
        
        // Create portfolios - Single B2B portfolio
        $portfolios = [
            [
                'organization_id' => $orgId,
                'name' => 'Cartera Empresarial',
                'description' => 'Clientes corporativos y empresas B2B',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
        
        foreach ($portfolios as $portfolio) {
            $this->db->table('portfolios')->insert($portfolio);
        }
        
        echo "Portfolios Created!\n";
    }
}