<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use App\Services\Zoho\ZohoService;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
public function create(array $input): User
{
    // Validate the input
    Validator::make($input, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'password' => $this->passwordRules(),
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
    ])->validate();



    // Create the user in the database
    $user = User::create([
        'name' => $input['name'],
        'email' => $input['email'],
        'password' => Hash::make($input['password']),
    ]);


    // Integrate with Zoho CRM
    try {
        $zohoService = new ZohoService();

        // Create the lead in Zoho CRM
        $lead = $zohoService->createLead(
            'signup', 
            $user->email, 
            $user->name, 
            $user->name,
        );
       //echo "<pre>"; print_r($lead);
       
    
        // Save Zoho ID in the user's record
        if (!empty($lead['data'][0]['details']['id'])) {
            
            $user->zoho_id = $lead['data'][0]['details']['id'];
            $user->save();
        }
    } catch (\Exception $e) {
        // Log the error for debugging
        \Log::error('Error creating Zoho lead: ' . $e->getMessage());
    }
      
    return $user;
}

}
