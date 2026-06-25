<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoice_number,
            'orderId' => $this->order_id,
            'order' => new OrderResource($this->whenLoaded('order')),
            'buyerCompanyId' => $this->buyer_company_id,
            'buyerCompany' => new CompanyResource($this->whenLoaded('buyerCompany')),
            'supplierCompanyId' => $this->supplier_company_id,
            'supplierCompany' => new CompanyResource($this->whenLoaded('supplierCompany')),
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'subtotal' => (float) $this->subtotal,
            'taxAmount' => (float) $this->tax_amount,
            'totalAmount' => (float) $this->total_amount,
            'paidAmount' => (float) $this->paid_amount,
            'remainingAmount' => (float) ($this->total_amount - $this->paid_amount),
            'filePath' => $this->file_path,
            'fileFormat' => $this->file_format,
            'downloadUrl' => $this->getDownloadUrl(),
            'isPaid' => $this->isPaid(),
            'isOverdue' => $this->isOverdue(),
            'generatedAt' => $this->generated_at->toISOString(),
            'sentAt' => $this->sent_at?->toISOString(),
            'paidAt' => $this->paid_at?->toISOString(),
            'dueDate' => $this->due_date?->toISOString(),
            'notes' => $this->notes,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}
