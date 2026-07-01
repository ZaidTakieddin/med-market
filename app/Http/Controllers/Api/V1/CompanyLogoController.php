<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Companies\CompanyLogoService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Companies\UploadCompanyLogoRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Company Logo', description: 'Company logo management')]
class CompanyLogoController extends BaseApiController
{
    public function __construct(
        private CompanyLogoService $companyLogoService
    ) {}

    /**
     * Upload or replace a company's logo.
     */
    #[OA\Post(
        path: '/companies/{company}/logo',
        summary: 'Upload company logo',
        description: 'Upload or replace the company logo (multipart field logo). Replacing removes the previous file. Allowed for admins or a user of the company.',
        tags: ['Company Logo'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['logo'],
                    properties: [
                        new OA\Property(property: 'logo', type: 'string', format: 'binary', description: 'JPEG/PNG/WebP image, max 5 MB.'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Logo updated successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(UploadCompanyLogoRequest $request, Company $company): JsonResponse
    {
        $this->authorize('manageLogo', $company);

        /** @var \Illuminate\Http\UploadedFile $logo */
        $logo = $request->file('logo');

        $company = $this->companyLogoService->setLogo($company, $logo);

        return $this->success(
            new CompanyResource($company),
            'Logo updated successfully'
        );
    }

    /**
     * Remove a company's logo.
     */
    #[OA\Delete(
        path: '/companies/{company}/logo',
        summary: 'Remove company logo',
        description: 'Remove the company logo and delete its stored file. Allowed for admins or a user of the company.',
        tags: ['Company Logo'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Logo removed successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('manageLogo', $company);

        $company = $this->companyLogoService->removeLogo($company);

        return $this->success(
            new CompanyResource($company),
            'Logo removed successfully'
        );
    }
}
