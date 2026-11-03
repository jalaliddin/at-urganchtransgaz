import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import ruAuth from './locales/ru/auth.json'
import ruCommon from './locales/ru/common.json'
import ruDashboard from './locales/ru/dashboard.json'
import ruNav from './locales/ru/nav.json'
import ruOrganizations from './locales/ru/organizations.json'
import ruTable from './locales/ru/table.json'
import ruValidation from './locales/ru/validation.json'
import uzAuth from './locales/uz/auth.json'
import uzCommon from './locales/uz/common.json'
import uzDashboard from './locales/uz/dashboard.json'
import uzNav from './locales/uz/nav.json'
import uzOrganizations from './locales/uz/organizations.json'
import uzTable from './locales/uz/table.json'
import uzValidation from './locales/uz/validation.json'

export const SUPPORTED_LANGUAGES = ['uz', 'ru'] as const
export type Language = (typeof SUPPORTED_LANGUAGES)[number]

const STORAGE_KEY = 'at.locale'

export const resources = {
  uz: { common: uzCommon, auth: uzAuth, validation: uzValidation, nav: uzNav, table: uzTable, dashboard: uzDashboard, organizations: uzOrganizations },
  ru: { common: ruCommon, auth: ruAuth, validation: ruValidation, nav: ruNav, table: ruTable, dashboard: ruDashboard, organizations: ruOrganizations },
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
