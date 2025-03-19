<?php

namespace App\Traits;

trait OrganizationTrait
{
    /**
     * Get the current organization ID for filtering
     * This ensures consistent behavior across all controllers
     * 
     * @return int|null Organization ID or null if no organization selected
     */
    protected function getCurrentOrganizationId()
    {
        // Always use Auth library since it handles different user roles 
        // (regular users, admins, superadmins with session selection)
        $auth = $this->auth;
        $currentOrgId = $auth->organizationId();
        
        // Log the organization context for debugging
        log_message('debug', '[OrganizationTrait] User ID: ' . $auth->user()['id'] . 
                   ', Role: ' . $auth->user()['role'] . 
                   ', Organization ID: ' . ($currentOrgId ?: 'none'));
        
        return $currentOrgId;
    }
    
    /**
     * Apply organization filter to a model query
     * This ensures consistent filtering across all controllers
     * 
     * @param object $model The model instance
     * @param int|null $organizationId The organization ID (optional, uses current if not provided)
     * @return object The model instance with filter applied
     */
    protected function applyOrganizationFilter($model, $organizationId = null)
    {
        // Use provided org ID or get current from Auth
        $orgId = $organizationId ?: $this->getCurrentOrganizationId();
        
        // Only apply filter if there's an organization ID
        if ($orgId) {
            // Reset any existing query to ensure clean state
            $model->resetQuery();
            
            try {
                // Check if this model has organization_id field by doing a test query
                $modelName = get_class($model);
                $tableName = $model->table;
                
                // Some models might not have organization_id field
                // Try to check if the field exists first
                $db = \Config\Database::connect();
                $prefix = $db->getPrefix();
                
                // Get table columns
                $columns = $db->getFieldNames($tableName);
                
                if (in_array('organization_id', $columns)) {
                    // Direct filter for tables with organization_id
                    $model->where($tableName . '.organization_id', $orgId);
                    log_message('debug', '[OrganizationTrait] Applied direct organization filter to ' . $tableName . ': ' . $orgId);
                } else {
                    // Special case for models without organization_id
                    if ($tableName === 'payments') {
                        // For payments, we need to join with invoices to filter by organization
                        // Skip filtering here, as the getByOrganization method handles this for payments
                        log_message('debug', '[OrganizationTrait] Skipping direct filter for payments table (needs JOIN with invoices)');
                    } else {
                        // Default case - try to apply the filter directly, but log a warning
                        log_message('warning', '[OrganizationTrait] Table ' . $tableName . ' may not have organization_id column, filter may fail');
                        $model->where('organization_id', $orgId);
                    }
                }
            } catch (\Exception $e) {
                // Handle the case where the table doesn't have organization_id
                log_message('error', '[OrganizationTrait] Error applying organization filter: ' . $e->getMessage());
            }
        } else {
            log_message('debug', '[OrganizationTrait] No organization filter applied');
        }
        
        return $model;
    }
    
    /**
     * Prepare common organization-related data for views
     * This ensures consistent data is available in all views
     * 
     * @param array $data Existing view data
     * @return array Updated view data with organization info
     */
    protected function prepareOrganizationData($data = [])
    {
        $auth = $this->auth;
        $currentOrgId = $this->getCurrentOrganizationId();
        
        // Base data that should be available in all views
        $data['auth'] = $auth;
        $data['current_organization_id'] = $currentOrgId;
        
        // For superadmins, add all organizations for dropdown
        if ($auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['all_organizations'] = $organizationModel->findAll();
            
            // Add current organization name if selected
            if ($currentOrgId) {
                $org = $organizationModel->find($currentOrgId);
                if ($org) {
                    $data['current_organization_name'] = $org['name'];
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Force refresh the organization context from session
     * This ensures the most current organization selection is used
     */
    protected function refreshOrganizationContext()
    {
        // Clear old input that might interfere with session values
        $this->session->remove('_ci_old_input');
        
        // Re-fetch the organization ID to ensure it's the most current
        $currentOrgId = $this->auth->organizationId();
        
        log_message('debug', '[OrganizationTrait] Refreshed organization context: ' . 
                   ($currentOrgId ?: 'none') . 
                   ', Session value: ' . ($this->session->get('selected_organization_id') ?: 'none'));
        
        return $currentOrgId;
    }
}