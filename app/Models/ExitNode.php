<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitNode extends Model
{
    protected $table = 'exit_nodes';

    protected $fillable = ['server_id', 'ip_address', 'public_iface', 'region', 'notes', 'cost_cents'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
