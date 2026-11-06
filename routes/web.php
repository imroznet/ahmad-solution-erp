<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ChallanController;
use App\Http\Controllers\CompanyAddressController;
use App\Http\Controllers\CompanyDocumentController;
use App\Http\Controllers\CompanyDocumentDownloadController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCategoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MushakController;
use App\Http\Controllers\PartyAddressController;
use App\Http\Controllers\PartyContactController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\QuotationApprovalController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationShareController;
use App\Http\Controllers\QuotationWorkflowController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\TaxRuleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WithholdingController;
use App\Tax\TaxType;
use Illuminate\Support\Facades\Route;

Route::middleware('quotation.share:read')->group(function (): void {
    Route::get('/q/{token}', [PublicQuotationController::class, 'show'])
        ->where('token', '[A-Za-z0-9_-]{43}')
        ->name('quotations.public.show');
    Route::get('/q/{token}/pdf', [PublicQuotationController::class, 'pdf'])
        ->where('token', '[A-Za-z0-9_-]{43}')
        ->name('quotations.public.pdf');
});

Route::post('/q/{token}/respond', [PublicQuotationController::class, 'respond'])
    ->middleware('quotation.share:respond')
    ->where('token', '[A-Za-z0-9_-]{43}')
    ->name('quotations.public.respond');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])
        ->middleware('permission:users.create')
        ->name('users.create');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:users.update')
        ->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.update');

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('permission:roles.manage')
        ->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.manage')
        ->name('roles.update');

    Route::get('/audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');

    Route::get('/system', SystemStatusController::class)
        ->middleware('permission:system.view')
        ->name('system.status');

    Route::get('/company', [CompanyProfileController::class, 'show'])
        ->middleware('permission:company.view')
        ->name('company.show');
    Route::get('/company/profile/edit', [CompanyProfileController::class, 'edit'])
        ->middleware('permission:company.update')
        ->name('company.edit');
    Route::put('/company/profile', [CompanyProfileController::class, 'update'])
        ->middleware('permission:company.update')
        ->name('company.update');
    Route::get('/company/profile/logo', [CompanyProfileController::class, 'logo'])
        ->middleware('permission:company.view')
        ->name('company.logo');

    Route::get('/company/addresses', [CompanyAddressController::class, 'index'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.index');
    Route::get('/company/addresses/create', [CompanyAddressController::class, 'create'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.create');
    Route::post('/company/addresses', [CompanyAddressController::class, 'store'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.store');
    Route::get('/company/addresses/{address}/edit', [CompanyAddressController::class, 'edit'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.edit');
    Route::put('/company/addresses/{address}', [CompanyAddressController::class, 'update'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.update');
    Route::post('/company/addresses/{address}/close', [CompanyAddressController::class, 'close'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.close');

    Route::get('/company/document-categories', [DocumentCategoryController::class, 'index'])
        ->middleware('permission:company.document_categories.manage')
        ->name('company.categories.index');
    Route::post('/company/document-categories', [DocumentCategoryController::class, 'store'])
        ->middleware('permission:company.document_categories.manage')
        ->name('company.categories.store');
    Route::put('/company/document-categories/{category}', [DocumentCategoryController::class, 'update'])
        ->middleware('permission:company.document_categories.manage')
        ->name('company.categories.update');

    Route::get('/company/documents', [CompanyDocumentController::class, 'index'])
        ->middleware('permission:company.documents.view')
        ->name('company.documents.index');
    Route::get('/company/documents/expiring', [CompanyDocumentController::class, 'expiring'])
        ->middleware('permission:company.documents.view')
        ->name('company.documents.expiring');
    Route::get('/company/documents/create', [CompanyDocumentController::class, 'create'])
        ->middleware('permission:company.documents.create')
        ->name('company.documents.create');
    Route::post('/company/documents', [CompanyDocumentController::class, 'store'])
        ->middleware('permission:company.documents.create')
        ->name('company.documents.store');
    Route::get('/company/documents/{document}', [CompanyDocumentController::class, 'show'])
        ->middleware('permission:company.documents.view')
        ->name('company.documents.show');
    Route::get('/company/documents/{document}/edit', [CompanyDocumentController::class, 'edit'])
        ->middleware('permission:company.documents.update')
        ->name('company.documents.edit');
    Route::put('/company/documents/{document}', [CompanyDocumentController::class, 'update'])
        ->middleware('permission:company.documents.update')
        ->name('company.documents.update');
    Route::post('/company/documents/{document}/versions', [CompanyDocumentController::class, 'storeVersion'])
        ->middleware('permission:company.documents.update')
        ->name('company.documents.versions.store');
    Route::post('/company/documents/{document}/retire', [CompanyDocumentController::class, 'destroy'])
        ->middleware('permission:company.documents.delete')
        ->name('company.documents.destroy');
    Route::get('/company/documents/{document}/versions/{version}/download', CompanyDocumentDownloadController::class)
        ->middleware('permission:company.documents.download')
        ->name('company.documents.download');

    Route::get('/customers', [PartyController::class, 'customers'])
        ->middleware('permission:customers.view')
        ->name('customers.index');
    Route::get('/customers/create', [PartyController::class, 'createCustomer'])
        ->middleware('permission:customers.manage')
        ->name('customers.create');
    Route::post('/customers', [PartyController::class, 'store'])
        ->middleware('permission:customers.manage')
        ->name('customers.store');

    Route::get('/suppliers', [PartyController::class, 'suppliers'])
        ->middleware('permission:suppliers.view')
        ->name('suppliers.index');
    Route::get('/suppliers/create', [PartyController::class, 'createSupplier'])
        ->middleware('permission:suppliers.manage')
        ->name('suppliers.create');
    Route::post('/suppliers', [PartyController::class, 'store'])
        ->middleware('permission:suppliers.manage')
        ->name('suppliers.store');

    Route::get('/parties', [PartyController::class, 'index'])
        ->middleware('permission:parties.view')
        ->name('parties.index');
    Route::get('/parties/create', [PartyController::class, 'create'])
        ->middleware('permission:parties.create')
        ->name('parties.create');
    Route::post('/parties', [PartyController::class, 'store'])
        ->middleware('permission:parties.create')
        ->name('parties.store');
    Route::get('/parties/{party}', [PartyController::class, 'show'])
        ->middleware('permission:parties.view,customers.view,suppliers.view')
        ->name('parties.show');
    Route::get('/parties/{party}/edit', [PartyController::class, 'edit'])
        ->middleware('permission:parties.update,customers.manage,suppliers.manage')
        ->name('parties.edit');
    Route::put('/parties/{party}', [PartyController::class, 'update'])
        ->middleware('permission:parties.update,customers.manage,suppliers.manage')
        ->name('parties.update');
    Route::post('/parties/{party}/archive', [PartyController::class, 'archive'])
        ->middleware('permission:parties.archive')
        ->name('parties.archive');

    Route::get('/parties/{party}/contacts/create', [PartyContactController::class, 'create'])
        ->middleware('permission:parties.contacts.manage')
        ->name('parties.contacts.create');
    Route::post('/parties/{party}/contacts', [PartyContactController::class, 'store'])
        ->middleware('permission:parties.contacts.manage')
        ->name('parties.contacts.store');
    Route::get('/parties/{party}/contacts/{contact}/edit', [PartyContactController::class, 'edit'])
        ->middleware('permission:parties.contacts.manage')
        ->name('parties.contacts.edit');
    Route::put('/parties/{party}/contacts/{contact}', [PartyContactController::class, 'update'])
        ->middleware('permission:parties.contacts.manage')
        ->name('parties.contacts.update');
    Route::post('/parties/{party}/contacts/{contact}/archive', [PartyContactController::class, 'archive'])
        ->middleware('permission:parties.contacts.manage')
        ->name('parties.contacts.archive');

    Route::get('/parties/{party}/addresses/create', [PartyAddressController::class, 'create'])
        ->middleware('permission:parties.addresses.manage')
        ->name('parties.addresses.create');
    Route::post('/parties/{party}/addresses', [PartyAddressController::class, 'store'])
        ->middleware('permission:parties.addresses.manage')
        ->name('parties.addresses.store');
    Route::get('/parties/{party}/addresses/{address}/edit', [PartyAddressController::class, 'edit'])
        ->middleware('permission:parties.addresses.manage')
        ->name('parties.addresses.edit');
    Route::put('/parties/{party}/addresses/{address}', [PartyAddressController::class, 'update'])
        ->middleware('permission:parties.addresses.manage')
        ->name('parties.addresses.update');
    Route::post('/parties/{party}/addresses/{address}/close', [PartyAddressController::class, 'close'])
        ->middleware('permission:parties.addresses.manage')
        ->name('parties.addresses.close');

    Route::get('/quotations', [QuotationController::class, 'index'])
        ->middleware('permission:quotations.view')
        ->name('quotations.index');
    Route::get('/quotations/create', [QuotationController::class, 'create'])
        ->middleware('permission:quotations.create')
        ->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])
        ->middleware('permission:quotations.create')
        ->name('quotations.store');
    Route::get('/quotations/approvals', [QuotationApprovalController::class, 'index'])
        ->middleware('permission:quotations.approve')
        ->name('quotations.approvals');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])
        ->middleware('permission:quotations.view')
        ->name('quotations.show');
    Route::get('/quotations/{quotation}/revisions/{revision}', [QuotationController::class, 'revision'])
        ->middleware('permission:quotations.view')
        ->whereNumber('revision')
        ->name('quotations.revisions.show');
    Route::get('/quotations/{quotation}/revisions/{revision}/pdf', [QuotationController::class, 'pdf'])
        ->middleware('permission:quotations.view')
        ->whereNumber('revision')
        ->name('quotations.pdf');
    Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])
        ->middleware('permission:quotations.update')
        ->name('quotations.edit');
    Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])
        ->middleware('permission:quotations.update')
        ->name('quotations.update');
    Route::post('/quotations/{quotation}/revisions', [QuotationController::class, 'storeRevision'])
        ->middleware('permission:quotations.update')
        ->name('quotations.revisions.store');
    Route::post('/quotations/{quotation}/submit', [QuotationWorkflowController::class, 'submit'])
        ->middleware('permission:quotations.submit')
        ->name('quotations.submit');
    Route::post('/quotations/{quotation}/approve', [QuotationWorkflowController::class, 'approve'])
        ->middleware('permission:quotations.approve')
        ->name('quotations.approve');
    Route::post('/quotations/{quotation}/return', [QuotationWorkflowController::class, 'returnToDraft'])
        ->middleware('permission:quotations.approve')
        ->name('quotations.return');
    Route::post('/quotations/{quotation}/send', [QuotationWorkflowController::class, 'send'])
        ->middleware('permission:quotations.send')
        ->name('quotations.send');
    Route::post('/quotations/{quotation}/cancel', [QuotationWorkflowController::class, 'cancel'])
        ->middleware('permission:quotations.cancel')
        ->name('quotations.cancel');
    Route::post('/quotations/{quotation}/expire', [QuotationWorkflowController::class, 'expire'])
        ->middleware('permission:quotations.update')
        ->name('quotations.expire');
    Route::post('/quotations/{quotation}/respond', [QuotationWorkflowController::class, 'respond'])
        ->middleware('permission:quotations.respond')
        ->name('quotations.respond');
    Route::post('/quotations/{quotation}/shares', [QuotationShareController::class, 'store'])
        ->middleware('permission:quotations.share')
        ->name('quotations.shares.store');
    Route::post('/quotations/{quotation}/shares/{share}/revoke', [QuotationShareController::class, 'revoke'])
        ->middleware('permission:quotations.share')
        ->name('quotations.shares.revoke');

    Route::get('/challans', [ChallanController::class, 'index'])
        ->middleware('permission:challans.view')
        ->name('challans.index');
    Route::get('/challans/create', [ChallanController::class, 'create'])
        ->middleware('permission:challans.create')
        ->name('challans.create');
    Route::post('/challans', [ChallanController::class, 'store'])
        ->middleware('permission:challans.create')
        ->name('challans.store');
    Route::get('/challans/{challan}', [ChallanController::class, 'show'])
        ->middleware('permission:challans.view')
        ->name('challans.show');
    Route::get('/challans/{challan}/edit', [ChallanController::class, 'edit'])
        ->middleware('permission:challans.update')
        ->name('challans.edit');
    Route::put('/challans/{challan}', [ChallanController::class, 'update'])
        ->middleware('permission:challans.update')
        ->name('challans.update');
    Route::post('/challans/{challan}/issue', [ChallanController::class, 'issue'])
        ->middleware('permission:challans.issue')
        ->name('challans.issue');
    Route::post('/challans/{challan}/dispatch', [ChallanController::class, 'dispatch'])
        ->middleware('permission:challans.dispatch')
        ->name('challans.dispatch');
    Route::post('/challans/{challan}/acknowledge', [ChallanController::class, 'acknowledge'])
        ->middleware('permission:challans.acknowledge')
        ->name('challans.acknowledge');
    Route::post('/challans/{challan}/void', [ChallanController::class, 'void'])
        ->middleware('permission:challans.void')
        ->name('challans.void');
    Route::get('/challans/{challan}/pdf', [ChallanController::class, 'pdf'])
        ->middleware('permission:challans.pdf')
        ->name('challans.pdf');

    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:invoices.view')
        ->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])
        ->middleware('permission:invoices.create')
        ->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware('permission:invoices.create')
        ->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('permission:invoices.view')
        ->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])
        ->middleware('permission:invoices.update')
        ->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])
        ->middleware('permission:invoices.update')
        ->name('invoices.update');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
        ->middleware('permission:invoices.issue')
        ->name('invoices.issue');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])
        ->middleware('permission:invoices.void')
        ->name('invoices.void');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->middleware('permission:invoices.pdf')
        ->name('invoices.pdf');

    Route::get('/tax-rules', [TaxRuleController::class, 'index'])
        ->middleware('permission:tax_rules.view')
        ->name('tax-rules.index');
    Route::get('/tax-rules/create', [TaxRuleController::class, 'create'])
        ->middleware('permission:tax_rules.manage')
        ->name('tax-rules.create');
    Route::post('/tax-rules', [TaxRuleController::class, 'store'])
        ->middleware('permission:tax_rules.manage')
        ->name('tax-rules.store');
    Route::get('/tax-rules/{taxRule}', [TaxRuleController::class, 'show'])
        ->middleware('permission:tax_rules.view')
        ->name('tax-rules.show');
    Route::get('/tax-rules/{taxRule}/edit', [TaxRuleController::class, 'edit'])
        ->middleware('permission:tax_rules.manage')
        ->name('tax-rules.edit');
    Route::put('/tax-rules/{taxRule}', [TaxRuleController::class, 'update'])
        ->middleware('permission:tax_rules.manage')
        ->name('tax-rules.update');
    Route::get('/tax-rules/{taxRule}/amend', [TaxRuleController::class, 'amendForm'])
        ->middleware('permission:tax_rules.manage')
        ->name('tax-rules.amend-form');
    Route::post('/tax-rules/{taxRule}/amend', [TaxRuleController::class, 'amend'])
        ->middleware('permission:tax_rules.manage')
        ->name('tax-rules.amend');
    Route::post('/tax-rules/{taxRule}/approve', [TaxRuleController::class, 'approve'])
        ->middleware('permission:tax_rules.approve')
        ->name('tax-rules.approve');
    Route::post('/tax-rules/{taxRule}/retire', [TaxRuleController::class, 'retire'])
        ->middleware('permission:tax_rules.retire')
        ->name('tax-rules.retire');

    Route::get('/mushak', [MushakController::class, 'index'])
        ->middleware('permission:mushak.view')
        ->name('mushak.index');
    Route::get('/mushak/create', [MushakController::class, 'create'])
        ->middleware('permission:mushak.create')
        ->name('mushak.create');
    Route::post('/mushak', [MushakController::class, 'store'])
        ->middleware('permission:mushak.create')
        ->name('mushak.store');
    Route::get('/mushak/{mushak}', [MushakController::class, 'show'])
        ->middleware('permission:mushak.view')
        ->name('mushak.show');
    Route::get('/mushak/{mushak}/edit', [MushakController::class, 'edit'])
        ->middleware('permission:mushak.update')
        ->name('mushak.edit');
    Route::put('/mushak/{mushak}', [MushakController::class, 'update'])
        ->middleware('permission:mushak.update')
        ->name('mushak.update');
    Route::post('/mushak/{mushak}/issue', [MushakController::class, 'issue'])
        ->middleware('permission:mushak.issue')
        ->name('mushak.issue');
    Route::post('/mushak/{mushak}/void', [MushakController::class, 'void'])
        ->middleware('permission:mushak.void')
        ->name('mushak.void');
    Route::get('/mushak/{mushak}/pdf', [MushakController::class, 'pdf'])
        ->middleware('permission:mushak.pdf')
        ->name('mushak.pdf');

    foreach ([
        'vds' => TaxType::VDS,
        'ait' => TaxType::AIT,
    ] as $prefix => $taxType) {
        Route::get('/'.$prefix, [WithholdingController::class, 'index'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.view')
            ->name($prefix.'.index');
        Route::get('/'.$prefix.'/create', [WithholdingController::class, 'create'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.manage')
            ->name($prefix.'.create');
        Route::post('/'.$prefix, [WithholdingController::class, 'store'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.manage')
            ->name($prefix.'.store');
        Route::get('/'.$prefix.'/{record}', [WithholdingController::class, 'show'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.view')
            ->whereNumber('record')
            ->name($prefix.'.show');
        Route::get('/'.$prefix.'/{record}/edit', [WithholdingController::class, 'edit'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.manage')
            ->whereNumber('record')
            ->name($prefix.'.edit');
        Route::put('/'.$prefix.'/{record}', [WithholdingController::class, 'update'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.manage')
            ->whereNumber('record')
            ->name($prefix.'.update');
        Route::post('/'.$prefix.'/{record}/void', [WithholdingController::class, 'void'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.manage')
            ->whereNumber('record')
            ->name($prefix.'.void');
        Route::get('/'.$prefix.'/{record}/pdf', [WithholdingController::class, 'pdf'])
            ->defaults('taxType', $taxType)
            ->middleware('permission:'.$prefix.'.pdf')
            ->whereNumber('record')
            ->name($prefix.'.pdf');
    }
});
