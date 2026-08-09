#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_09_000002_create_agent_beneficial_owners_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_beneficial_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('identification_type')->nullable();
            $table->string('identification_number')->nullable();
            $table->decimal('ownership_percentage', 5, 2)->default(0);
            $table->boolean('is_director')->default(false);
            $table->boolean('is_pep')->default(false);
            $table->boolean('sanctions_match')->default(false);
            $table->string('screening_status')->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_beneficial_owners');
    }
};
MBOS_EOF

cat > database/migrations/2026_08_09_000003_create_agent_documents_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->string('storage_path')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('verification_status')->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_documents');
    }
};
MBOS_EOF

cat > app/Models/AgentBeneficialOwner.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBeneficialOwner extends Model
{
    protected $fillable = [
        'agent_id',
        'full_name',
        'date_of_birth',
        'nationality',
        'identification_type',
        'identification_number',
        'ownership_percentage',
        'is_director',
        'is_pep',
        'sanctions_match',
        'screening_status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_director' => 'boolean',
        'is_pep' => 'boolean',
        'sanctions_match' => 'boolean',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
MBOS_EOF

cat > app/Models/AgentDocument.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentDocument extends Model
{
    protected $fillable = [
        'agent_id',
        'document_type',
        'document_number',
        'storage_path',
        'issued_at',
        'expires_at',
        'verification_status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
MBOS_EOF

cat > app/Models/Agent.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agent_code',
        'agent_type',
        'legal_name',
        'trading_name',
        'registration_number',
        'tax_identification_number',
        'phone',
        'email',
        'branch_id',
        'supervisor_id',
        'status',
        'kyc_status',
        'risk_rating',
        'exclusive_relationship',
        'principal_reference',
        'daily_transaction_limit',
        'daily_cash_out_limit',
        'single_transaction_limit',
        'next_review_date',
        'created_by',
        'approved_by',
        'approved_at',
        'activated_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'exclusive_relationship' => 'boolean',
        'next_review_date' => 'date',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function owners()
    {
        return $this->hasMany(AgentBeneficialOwner::class);
    }

    public function documents()
    {
        return $this->hasMany(AgentDocument::class);
    }
}
MBOS_EOF

cat > app/Services/Payments/AgentApprovalService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Exception;

class AgentApprovalService
{
    /**
     * @throws Exception
     */
    public function submit(Agent $agent, int $submittedBy): Agent
    {
        if ($agent->status !== AgentStatus::DRAFT->value) {
            throw new Exception("Agent {$agent->agent_code} is not in DRAFT status.");
        }

        $agent->update([
            'status' => AgentStatus::PENDING_KYC->value,
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function approve(Agent $agent, int $approvedBy): Agent
    {
        if ($agent->status !== AgentStatus::PENDING_APPROVAL->value) {
            throw new Exception("Agent {$agent->agent_code} is not pending approval.");
        }

        if ($agent->created_by === $approvedBy) {
            throw new Exception('The registering officer cannot approve their own agent.');
        }

        $agent->update([
            'status' => AgentStatus::APPROVED->value,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function reject(Agent $agent, int $rejectedBy, string $reason): Agent
    {
        if ($agent->status !== AgentStatus::PENDING_APPROVAL->value) {
            throw new Exception("Agent {$agent->agent_code} is not pending approval.");
        }

        if ($agent->created_by === $rejectedBy) {
            throw new Exception('The registering officer cannot reject their own agent.');
        }

        $agent->update([
            'status' => AgentStatus::REJECTED->value,
            'approved_by' => $rejectedBy,
            'suspension_reason' => $reason,
        ]);

        return $agent->fresh();
    }
}
MBOS_EOF

cat > app/Services/Payments/AgentKycService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Exception;

class AgentKycService
{
    /**
     * @throws Exception
     */
    public function completeKyc(Agent $agent, int $reviewedBy): Agent
    {
        if ($agent->status !== AgentStatus::PENDING_KYC->value) {
            throw new Exception("Agent {$agent->agent_code} is not pending KYC.");
        }

        if ($agent->created_by === $reviewedBy) {
            throw new Exception('The registering officer cannot complete KYC review for their own agent.');
        }

        if ($agent->owners()->count() === 0) {
            throw new Exception('At least one beneficial owner must be recorded before KYC can be completed.');
        }

        if ($agent->documents()->count() === 0) {
            throw new Exception('At least one document must be recorded before KYC can be completed.');
        }

        $agent->update([
            'status' => AgentStatus::PENDING_LOCATION_VERIFICATION->value,
            'kyc_status' => 'COMPLETED',
        ]);

        return $agent->fresh();
    }
}
MBOS_EOF

cat > app/Http/Requests/Agent/AddAgentOwnerRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddAgentOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:2'],
            'identification_type' => ['nullable', 'string'],
            'identification_number' => ['nullable', 'string'],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_director' => ['nullable', 'boolean'],
            'is_pep' => ['nullable', 'boolean'],
            'sanctions_match' => ['nullable', 'boolean'],
        ];
    }
}
MBOS_EOF

cat > app/Http/Requests/Agent/AddAgentDocumentRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddAgentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string'],
            'document_number' => ['nullable', 'string'],
            'storage_path' => ['nullable', 'string'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
MBOS_EOF

echo "AG-02 Part 1 of 2 applied (migrations, models, services, requests)."