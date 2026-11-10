# API reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/api`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

API is an **Available**, **No schema impact** Capell package in the **Capell Publishing** product group. It ships as `capell-app/api` and extends these surfaces: frontend.

- Status: Available
- Tier: free
- Bundle: publishing
- Composer package: `capell-app/api`
- Namespace: `Capell\Api`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\Api\Providers\ApiServiceProvider`

### Config files

- `packages/api/config/capell-api.php`

### Route files

- `packages/api/routes/api.php`

### Listeners

- `QueuePageRevalidation`

### Actions

- `BuildPublicLayoutPayloadAction`
- `BuildPublicPagePayloadAction`
- `ResolveApiRequestContextAction`
- `BuildPageDataAction`
- `ListPagesAction`
- `ProjectPageResourcesAction`
- `QueueRevalidationWebhooksAction`
- `ResolvePageSourceAction`
- `ResolvePreviewAccessAction`
- `ValidatePublicLayoutPayloadBoundsAction`

### Data objects

- `LegacyPageResponseData`
- `PublicPagePayloadOptionsData`
- `ResolveApiRequestContextData`
- `ErrorData`
- `ListMetaData`
- `MediaData`
- `NavigationLinkData`
- `PageData`
- `PageListData`
- `PageResponseData`
- `PreviewAccessData`
- `ResourceListData`
- `RevalidationWebhookData`

### Jobs

- `DeliverRevalidationWebhookJob`

### Manifest contributions

- `health-check: Capell\Api\Health\ApiHealthCheck`
- `route: Capell\Api\Manifest\ApiRoutesContribution`

### Health checks

- `Capell\Api\Health\ApiHealthCheck`

### Cache tags

- `api`
- `api:pages`


## Data Model

This package has no schema impact. It extends Capell through `health-check` contributions and `route` contributions instead of declaring package-owned tables.

<!-- prettier-ignore-end -->
