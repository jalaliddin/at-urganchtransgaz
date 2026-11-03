import dayjs from 'dayjs'
import 'dayjs/locale/ru'
import 'dayjs/locale/uz-latn'
import customParseFormat from 'dayjs/plugin/customParseFormat'
import timezone from 'dayjs/plugin/timezone'
import utc from 'dayjs/plugin/utc'

dayjs.extend(utc)
dayjs.extend(timezone)
dayjs.extend(customParseFormat)

/** Every date shown to users is in Tashkent time; the API speaks UTC. */
export const APP_TIMEZONE = 'Asia/Tashkent'

export const DAYJS_LOCALES = { uz: 'uz-latn', ru: 'ru' } as const

export default dayjs
