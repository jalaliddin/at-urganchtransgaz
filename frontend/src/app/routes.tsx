import type { RouteObject } from 'react-router'
import { ChangePasswordPage } from '@/features/auth/ChangePasswordPage'
import { ForgotPasswordPage } from '@/features/auth/ForgotPasswordPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { RequirePasswordChanged } from '@/features/auth/RequirePasswordChanged'
import { RequirePermission } from '@/features/auth/RequirePermission'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { DepartmentsPage } from '@/features/departments/DepartmentsPage'
import { EmployeeDetailPage } from '@/features/employees/EmployeeDetailPage'
import { EmployeesPage } from '@/features/employees/EmployeesPage'
import { NewTicketPage } from '@/features/helpdesk/NewTicketPage'
import { TicketDetailPage } from '@/features/helpdesk/TicketDetailPage'
import { HelpdeskReportPage } from '@/features/helpdesk/HelpdeskReportPage'
import { HelpdeskSettingsPage } from '@/features/helpdesk/HelpdeskSettingsPage'
import { DocumentDetailPage } from '@/features/documents/DocumentDetailPage'
import { DocumentsPage } from '@/features/documents/DocumentsPage'
import { ExportsPage } from '@/features/exports/ExportsPage'
import { AssetDetailPage } from '@/features/assets/AssetDetailPage'
import { AssetsPage } from '@/features/assets/AssetsPage'
import { AssetReportPage } from '@/features/assets/AssetReportPage'
import { AssetSettingsPage } from '@/features/assets/AssetSettingsPage'
import { DisposalsPage } from '@/features/assets/DisposalsPage'
import { InventoryPage } from '@/features/assets/InventoryPage'
import { InventorySessionPage } from '@/features/assets/InventorySessionPage'
import { LabelsPage } from '@/features/assets/LabelsPage'
import { LookupPage } from '@/features/assets/LookupPage'
import { MyAssetsPage } from '@/features/assets/MyAssetsPage'
import { TransferDetailPage } from '@/features/assets/TransferDetailPage'
import { TransfersPage } from '@/features/assets/TransfersPage'
import { RequisitionDetailPage } from '@/features/requisitions/RequisitionDetailPage'
import { RequisitionsPage } from '@/features/requisitions/RequisitionsPage'
import { AgentsPage } from '@/features/software/AgentsPage'
import { CompliancePage } from '@/features/software/CompliancePage'
import { ImportPage } from '@/features/software/ImportPage'
import { LicensesPage } from '@/features/software/LicensesPage'
import { SoftwareDetailPage } from '@/features/software/SoftwareDetailPage'
import { SoftwarePage } from '@/features/software/SoftwarePage'
import { BalancesPage } from '@/features/warehouse/BalancesPage'
import { CountPage } from '@/features/warehouse/CountPage'
import { CountsPage } from '@/features/warehouse/CountsPage'
import { MovementsPage } from '@/features/warehouse/MovementsPage'
import { StockDocumentPage } from '@/features/warehouse/StockDocumentPage'
import { StockDocumentsPage } from '@/features/warehouse/StockDocumentsPage'
import { WarehouseSettingsPage } from '@/features/warehouse/WarehouseSettingsPage'
import { TicketsPage } from '@/features/helpdesk/TicketsPage'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { OrganizationDetailPage } from '@/features/organizations/OrganizationDetailPage'
import { OrganizationsPage } from '@/features/organizations/OrganizationsPage'
import { RoleEditorPage } from '@/features/roles/RoleEditorPage'
import { RolesPage } from '@/features/roles/RolesPage'
import { UserDetailPage } from '@/features/users/UserDetailPage'
import { UsersPage } from '@/features/users/UsersPage'
import { AuditLogPage } from '@/features/audit/AuditLogPage'
import { NotificationsPage } from '@/features/notifications/NotificationsPage'
import { ProfilePage } from '@/features/profile/ProfilePage'
import { SettingsPage } from '@/features/settings/SettingsPage'
import { AppLayout } from '@/layouts/AppLayout/AppLayout'

export const routes: RouteObject[] = [
  { path: '/login', element: <LoginPage /> },
  { path: '/forgot-password', element: <ForgotPasswordPage /> },
  { path: '/reset-password', element: <ResetPasswordPage /> },
  {
    element: <RequireAuth />,
    children: [
      { path: '/change-password', element: <ChangePasswordPage /> },
      {
        element: <RequirePasswordChanged />,
        children: [
          {
            path: '/assets/labels',
            element: <RequirePermission permission="assets.view" />,
            children: [{ index: true, element: <LabelsPage /> }],
          },
          {
            element: <AppLayout />,
            handle: { crumb: 'nav:header.home' },
            children: [
              { index: true, element: <DashboardPage /> },
              {
                path: 'helpdesk',
                element: <RequirePermission permission={['helpdesk.view', 'helpdesk.create']} />,
                handle: { crumb: 'nav:items.helpdesk' },
                children: [
                  { index: true, element: <TicketsPage /> },
                  {
                    path: 'new',
                    element: <RequirePermission permission="helpdesk.create" />,
                    handle: { crumb: 'helpdesk:new.title' },
                    children: [{ index: true, element: <NewTicketPage /> }],
                  },
                  {
                    path: 'settings',
                    element: <RequirePermission permission="helpdesk.manage" />,
                    handle: { crumb: 'nav:items.helpdeskSettings' },
                    children: [{ index: true, element: <HelpdeskSettingsPage /> }],
                  },
                  {
                    path: 'reports',
                    element: <RequirePermission permission="helpdesk.view" />,
                    handle: { crumb: 'nav:items.helpdeskReports' },
                    children: [{ index: true, element: <HelpdeskReportPage /> }],
                  },
                  { path: ':id', element: <TicketDetailPage />, handle: { crumb: 'helpdesk:crumb' } },
                ],
              },
              {
                path: 'assets',
                handle: { crumb: 'nav:items.assets' },
                children: [
                  { path: 'mine', element: <MyAssetsPage />, handle: { crumb: 'nav:items.myAssets' } },
                  { path: 'lookup/:inventory', element: <LookupPage />, handle: { crumb: 'assets:crumb' } },
                  {
                    path: 'inventory',
                    element: <RequirePermission permission={['assets.view', 'assets.inventory']} />,
                    handle: { crumb: 'nav:items.inventory' },
                    children: [
                      { index: true, element: <InventoryPage /> },
                      {
                        path: ':id',
                        element: <InventorySessionPage />,
                        handle: { crumb: 'assets:inventory.title' },
                      },
                    ],
                  },
                  {
                    path: 'settings',
                    element: <RequirePermission permission={['assets.manage_catalog', 'assets.update']} />,
                    handle: { crumb: 'nav:items.assetSettings' },
                    children: [{ index: true, element: <AssetSettingsPage /> }],
                  },
                  {
                    element: <RequirePermission permission="assets.view" />,
                    children: [
                      { index: true, element: <AssetsPage /> },
                      {
                        path: 'report',
                        element: <AssetReportPage />,
                        handle: { crumb: 'nav:items.assetReport' },
                      },
                      {
                        path: 'disposals',
                        element: <DisposalsPage />,
                        handle: { crumb: 'nav:items.disposals' },
                      },
                      {
                        path: 'transfers',
                        handle: { crumb: 'nav:items.assetTransfers' },
                        children: [
                          { index: true, element: <TransfersPage /> },
                          {
                            path: ':id',
                            element: <TransferDetailPage />,
                            handle: { crumb: 'assets:transfers.number' },
                          },
                        ],
                      },
                      { path: ':id', element: <AssetDetailPage />, handle: { crumb: 'assets:crumb' } },
                    ],
                  },
                ],
              },
              {
                path: 'software',
                element: (
                  <RequirePermission permission={['software.view', 'software.install', 'software.manage']} />
                ),
                handle: { crumb: 'nav:items.software' },
                children: [
                  { index: true, element: <SoftwarePage /> },
                  { path: 'licenses', element: <LicensesPage />, handle: { crumb: 'nav:items.licenses' } },
                  {
                    path: 'compliance',
                    element: <CompliancePage />,
                    handle: { crumb: 'nav:items.compliance' },
                  },
                  { path: 'agents', element: <AgentsPage />, handle: { crumb: 'nav:items.agents' } },
                  { path: 'import', element: <ImportPage />, handle: { crumb: 'nav:items.softwareImport' } },
                  { path: ':id', element: <SoftwareDetailPage />, handle: { crumb: 'software:crumb' } },
                ],
              },
              {
                path: 'requisitions',
                element: <RequirePermission permission={['requisitions.view', 'requisitions.create']} />,
                handle: { crumb: 'nav:items.requisitions' },
                children: [
                  { index: true, element: <RequisitionsPage /> },
                  {
                    path: ':id',
                    element: <RequisitionDetailPage />,
                    handle: { crumb: 'requisitions:crumb' },
                  },
                ],
              },
              {
                path: 'warehouse',
                element: <RequirePermission permission="warehouse.view" />,
                handle: { crumb: 'nav:items.warehouse' },
                children: [
                  { index: true, element: <BalancesPage /> },
                  {
                    path: 'documents',
                    handle: { crumb: 'nav:items.stockDocuments' },
                    children: [
                      { index: true, element: <StockDocumentsPage /> },
                      { path: ':id', element: <StockDocumentPage />, handle: { crumb: 'warehouse:crumb' } },
                    ],
                  },
                  {
                    path: 'movements',
                    element: <MovementsPage />,
                    handle: { crumb: 'nav:items.stockMovements' },
                  },
                  {
                    path: 'counts',
                    handle: { crumb: 'nav:items.stockCounts' },
                    children: [
                      { index: true, element: <CountsPage /> },
                      { path: ':id', element: <CountPage />, handle: { crumb: 'warehouse:countCrumb' } },
                    ],
                  },
                  {
                    path: 'settings',
                    element: <WarehouseSettingsPage />,
                    handle: { crumb: 'nav:items.warehouseSettings' },
                  },
                ],
              },
              {
                path: 'documents',
                element: <RequirePermission permission="documents.view" />,
                handle: { crumb: 'nav:items.documents' },
                children: [
                  { index: true, element: <DocumentsPage /> },
                  { path: ':id', element: <DocumentDetailPage />, handle: { crumb: 'documents:crumb' } },
                ],
              },
              {
                path: 'exports',
                element: <ExportsPage />,
                handle: { crumb: 'nav:items.exports' },
              },
              {
                path: 'organizations',
                element: <RequirePermission permission="organizations.view" />,
                handle: { crumb: 'nav:items.organizations' },
                children: [
                  { index: true, element: <OrganizationsPage /> },
                  {
                    path: ':id',
                    element: <OrganizationDetailPage />,
                    handle: { crumb: 'organizations:crumb' },
                  },
                ],
              },
              {
                path: 'departments',
                element: <RequirePermission permission="departments.view" />,
                handle: { crumb: 'nav:items.departments' },
                children: [{ index: true, element: <DepartmentsPage /> }],
              },
              {
                path: 'employees',
                element: <RequirePermission permission="employees.view" />,
                handle: { crumb: 'nav:items.employees' },
                children: [
                  { index: true, element: <EmployeesPage /> },
                  { path: ':id', element: <EmployeeDetailPage />, handle: { crumb: 'employees:crumb' } },
                ],
              },
              {
                path: 'users',
                element: <RequirePermission permission="users.view" />,
                handle: { crumb: 'nav:items.users' },
                children: [
                  { index: true, element: <UsersPage /> },
                  { path: ':id', element: <UserDetailPage />, handle: { crumb: 'users:crumb' } },
                ],
              },
              {
                path: 'roles',
                element: <RequirePermission permission="roles.view" />,
                handle: { crumb: 'nav:items.roles' },
                children: [
                  { index: true, element: <RolesPage /> },
                  { path: 'new', element: <RoleEditorPage />, handle: { crumb: 'roles:form.createTitle' } },
                  { path: ':id', element: <RoleEditorPage />, handle: { crumb: 'roles:crumb' } },
                ],
              },
              {
                path: 'audit',
                element: <RequirePermission permission="audit.view" />,
                handle: { crumb: 'nav:items.audit' },
                children: [{ index: true, element: <AuditLogPage /> }],
              },
              {
                path: 'settings',
                element: <RequirePermission permission="settings.view" />,
                handle: { crumb: 'nav:items.settings' },
                children: [{ index: true, element: <SettingsPage /> }],
              },
              { path: 'profile', element: <ProfilePage />, handle: { crumb: 'nav:items.profile' } },
              {
                path: 'notifications',
                element: <NotificationsPage />,
                handle: { crumb: 'nav:items.notifications' },
              },
              { path: '*', element: <NotFoundPage /> },
            ],
          },
        ],
      },
    ],
  },
]
