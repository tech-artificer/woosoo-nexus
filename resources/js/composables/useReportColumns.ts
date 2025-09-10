import type { ColumnDef } from '@tanstack/vue-table'


export default function useReportColumns() {
  const commonNumberCell = (info: any) => new Intl.NumberFormat().format(info.getValue() ?? 0)

  const getColumns = (type: string): ColumnDef<any, any>[] => {
    switch (type) {
      case 'sales':
        return [
          { accessorKey: 'date', header: 'Date', cell: (i) => new Date(i.getValue()).toLocaleDateString(), enableColumnFilter: true },
          { accessorKey: 'total', header: 'Total Sales', cell: commonNumberCell, enableColumnFilter: true },
          { accessorKey: 'transactions', header: 'Transactions', cell: commonNumberCell, enableColumnFilter: true },
        ]
      default:
        return []
    }
  }

  return { getColumns }
}