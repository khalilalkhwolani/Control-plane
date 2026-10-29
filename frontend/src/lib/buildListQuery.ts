export function buildListQuery(filters: Record<string, string | number | undefined>, page: number): string {
  const params = new URLSearchParams({ page: String(page) })
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') {
      params.set(key, String(value))
    }
  }
  return params.toString()
}
