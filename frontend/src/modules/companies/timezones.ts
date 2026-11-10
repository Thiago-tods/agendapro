import type { SelectOption } from '@/components/ui/AppSelect.vue'

/** IANA timezones in use in Brazil, labelled by a reference city. */
export const BRAZIL_TIMEZONES: SelectOption[] = [
  { value: 'America/Sao_Paulo', label: 'Brasília / São Paulo (UTC−3)' },
  { value: 'America/Bahia', label: 'Salvador (UTC−3)' },
  { value: 'America/Fortaleza', label: 'Fortaleza (UTC−3)' },
  { value: 'America/Recife', label: 'Recife (UTC−3)' },
  { value: 'America/Maceio', label: 'Maceió (UTC−3)' },
  { value: 'America/Belem', label: 'Belém (UTC−3)' },
  { value: 'America/Araguaina', label: 'Palmas / Araguaína (UTC−3)' },
  { value: 'America/Santarem', label: 'Santarém (UTC−3)' },
  { value: 'America/Manaus', label: 'Manaus (UTC−4)' },
  { value: 'America/Cuiaba', label: 'Cuiabá (UTC−4)' },
  { value: 'America/Campo_Grande', label: 'Campo Grande (UTC−4)' },
  { value: 'America/Porto_Velho', label: 'Porto Velho (UTC−4)' },
  { value: 'America/Boa_Vista', label: 'Boa Vista (UTC−4)' },
  { value: 'America/Rio_Branco', label: 'Rio Branco (UTC−5)' },
  { value: 'America/Noronha', label: 'Fernando de Noronha (UTC−2)' },
]
