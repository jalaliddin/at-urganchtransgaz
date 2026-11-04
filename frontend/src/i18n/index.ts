import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import ruAudit from './locales/ru/audit.json'
import ruAuth from './locales/ru/auth.json'
import ruCommon from './locales/ru/common.json'
import ruDashboard from './locales/ru/dashboard.json'
import ruDepartments from './locales/ru/departments.json'
import ruEmployees from './locales/ru/employees.json'
import ruNav from './locales/ru/nav.json'
import ruNotifications from './locales/ru/notifications.json'
import ruOrganizations from './locales/ru/organizations.json'
import ruPermissions from './locales/ru/permissions.json'
import ruProfile from './locales/ru/profile.json'
import ruRoles from './locales/ru/roles.json'
import ruSettings from './locales/ru/settings.json'
import ruTable from './locales/ru/table.json'
import ruUsers from './locales/ru/users.json'
import ruValidation from './locales/ru/validation.json'
import ruExports from './locales/ru/exports.json'
import ruDocuments from './locales/ru/documents.json'
import ruHelpdesk from './locales/ru/helpdesk.json'
import uzAudit from './locales/uz/audit.json'
import uzAuth from './locales/uz/auth.json'
import uzCommon from './locales/uz/common.json'
import uzDashboard from './locales/uz/dashboard.json'
import uzDepartments from './locales/uz/departments.json'
import uzEmployees from './locales/uz/employees.json'
import uzNav from './locales/uz/nav.json'
import uzNotifications from './locales/uz/notifications.json'
import uzOrganizations from './locales/uz/organizations.json'
import uzPermissions from './locales/uz/permissions.json'
import uzProfile from './locales/uz/profile.json'
import uzRoles from './locales/uz/roles.json'
import uzSettings from './locales/uz/settings.json'
import uzTable from './locales/uz/table.json'
import uzUsers from './locales/uz/users.json'
import uzValidation from './locales/uz/validation.json'
import uzExports from './locales/uz/exports.json'
import uzDocuments from './locales/uz/documents.json'
import uzHelpdesk from './locales/uz/helpdesk.json'

export const SUPPORTED_LANGUAGES = ['uz', 'ru'] as const
export type Language = (typeof SUPPORTED_LANGUAGES)[number]

const STORAGE_KEY = 'at.locale'

export const resources = {
  uz: {
    common: uzCommon,
    auth: uzAuth,
    validation: uzValidation,
    nav: uzNav,
    table: uzTable,
    dashboard: uzDashboard,
    organizations: uzOrganizations,
    departments: uzDepartments,
    employees: uzEmployees,
    users: uzUsers,
    roles: uzRoles,
    permissions: uzPermissions,
    audit: uzAudit,
    settings: uzSettings,
    profile: uzProfile,
    notifications: uzNotifications,
    exports: uzExports,
    documents: uzDocuments,
    helpdesk: uzHelpdesk,
  },
  ru: {
    common: ruCommon,
    auth: ruAuth,
    validation: ruValidation,
    nav: ruNav,
    table: ruTable,
    dashboard: ruDashboard,
    organizations: ruOrganizations,
    departments: ruDepartments,
    employees: ruEmployees,
    users: ruUsers,
    roles: ruRoles,
    permissions: ruPermissions,
    audit: ruAudit,
    settings: ruSettings,
    profile: ruProfile,
    notifications: ruNotifications,
    exports: ruExports,
    documents: ruDocuments,
    helpdesk: ruHelpdesk,
  },
} as const

export function isLanguage(value: unknown): value is Language {
  return typeof value === 'string' && (SUPPORTED_LANGUAGES as readonly string[]).includes(value)
}

function storedLanguage(): Language {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY)
    return isLanguage(value) ? value : 'uz'
  } catch {
    return 'uz'
  }
}

/** Switches the UI language and remembers it on this device. */
export async function setLanguage(language: Language): Promise<void> {
  try {
    window.localStorage.setItem(STORAGE_KEY, language)
  } catch {
    // storage unavailable (private mode) — the choice still applies to this session
  }
  if (i18n.language !== language) {
    await i18n.changeLanguage(language)
  }
}

void i18n.use(initReactI18next).init({
  resources,
  lng: import.meta.env.MODE === 'test' ? 'uz' : storedLanguage(),
  fallbackLng: 'uz',
  defaultNS: 'common',
  ns: Object.keys(resources.uz),
  interpolation: { escapeValue: false },
  returnNull: false,
})

export default i18n
