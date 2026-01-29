import { useState, useEffect, useRef } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { CrudTable } from '@/components/CrudTable';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import { RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export default function ActivityLogs() {
    const { t } = useTranslation();
    const { auth, activityLogs, types, users, filters: pageFilters = {} } = usePage().props as any;

    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedUser, setSelectedUser] = useState(pageFilters.user_id || 'all');
    const [selectedType, setSelectedType] = useState(pageFilters.type || 'all');
    const [dateFrom, setDateFrom] = useState<Date | undefined>(pageFilters.date_from ? new Date(pageFilters.date_from) : undefined);
    const [dateTo, setDateTo] = useState<Date | undefined>(pageFilters.date_to ? new Date(pageFilters.date_to) : undefined);
    const [showFilters, setShowFilters] = useState(false);
    const [selectedLog, setSelectedLog] = useState<any>(null);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
    const lastLogIdRef = useRef<number | null>(null);

    const hasActiveFilters = () => {
        return selectedUser !== 'all' || selectedType !== 'all' || !!dateFrom || !!dateTo || searchTerm !== '';
    };

    const activeFilterCount = () => {
        let count = 0;
        if (selectedUser !== 'all') count++;
        if (selectedType !== 'all') count++;
        if (dateFrom) count++;
        if (dateTo) count++;
        if (searchTerm) count++;
        return count;
    };

    const formatDateParam = (d?: Date) => {
        if (!d) return undefined;
        // YYYY-MM-DD (server expects date string)
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const applyFilters = () => {
        const params: any = { page: 1 };

        if (searchTerm) params.search = searchTerm;
        if (selectedUser !== 'all') params.user_id = selectedUser;
        if (selectedType !== 'all') params.type = selectedType;
        const dateFromParam = formatDateParam(dateFrom);
        const dateToParam = formatDateParam(dateTo);
        if (dateFromParam) params.date_from = dateFromParam;
        if (dateToParam) params.date_to = dateToParam;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;

        router.get(route('admin.activity-logs.index'), params, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedUser('all');
        setSelectedType('all');
        setDateFrom(undefined);
        setDateTo(undefined);
        router.get(route('admin.activity-logs.index'), { page: 1 }, { preserveState: true, preserveScroll: true });
    };

    const handleSort = (field: string) => {
        const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
        const params: any = {
            sort_field: field,
            sort_direction: direction,
            page: 1,
        };

        if (searchTerm) params.search = searchTerm;
        if (selectedUser !== 'all') params.user_id = selectedUser;
        if (selectedType !== 'all') params.type = selectedType;
        const dateFromParam = formatDateParam(dateFrom);
        const dateToParam = formatDateParam(dateTo);
        if (dateFromParam) params.date_from = dateFromParam;
        if (dateToParam) params.date_to = dateToParam;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;

        router.get(route('admin.activity-logs.index'), params, { preserveState: true, preserveScroll: true });
    };

    const handleViewDetails = (log: any) => {
        setSelectedLog(log);
        setIsDetailModalOpen(true);
    };

    // Store the latest log ID on initial load
    useEffect(() => {
        if (activityLogs?.data && activityLogs.data.length > 0 && !lastLogIdRef.current) {
            lastLogIdRef.current = activityLogs.data[0].id;
        }
    }, [activityLogs]);

    // Auto-refresh interval - check for new logs every 5 seconds
    useEffect(() => {
        if (!autoRefreshEnabled) {
            return;
        }

        const refreshInterval = setInterval(() => {
            if (!isRefreshing) {
                setIsRefreshing(true);
                
                // Build params with current filters
                const params: any = {};
                if (searchTerm) params.search = searchTerm;
                if (selectedUser !== 'all') params.user_id = selectedUser;
                if (selectedType !== 'all') params.type = selectedType;
                const dateFromParam = formatDateParam(dateFrom);
                const dateToParam = formatDateParam(dateTo);
                if (dateFromParam) params.date_from = dateFromParam;
                if (dateToParam) params.date_to = dateToParam;
                if (pageFilters.per_page) params.per_page = pageFilters.per_page;
                if (pageFilters.sort_field) {
                    params.sort_field = pageFilters.sort_field;
                    params.sort_direction = pageFilters.sort_direction;
                }
                
                // Reload only activity logs data without full page refresh
                router.get(route('admin.activity-logs.index'), params, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activityLogs'],
                    onFinish: () => {
                        setIsRefreshing(false);
                    }
                });
            }
        }, 5000); // Refresh every 5 seconds

        return () => clearInterval(refreshInterval);
    }, [autoRefreshEnabled, isRefreshing, searchTerm, selectedUser, selectedType, dateFrom, dateTo, pageFilters]);

    const handleManualRefresh = () => {
        setIsRefreshing(true);
        const params: any = {};
        if (searchTerm) params.search = searchTerm;
        if (selectedUser !== 'all') params.user_id = selectedUser;
        if (selectedType !== 'all') params.type = selectedType;
        const dateFromParam = formatDateParam(dateFrom);
        const dateToParam = formatDateParam(dateTo);
        if (dateFromParam) params.date_from = dateFromParam;
        if (dateToParam) params.date_to = dateToParam;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_field) {
            params.sort_field = pageFilters.sort_field;
            params.sort_direction = pageFilters.sort_direction;
        }
        
        router.get(route('admin.activity-logs.index'), params, {
            preserveState: true,
            preserveScroll: true,
            only: ['activityLogs'],
            onFinish: () => {
                setIsRefreshing(false);
            }
        });
    };

    const columns = [
        {
            key: 'id',
            label: t('ID'),
            sortable: true,
        },
        {
            key: 'user.name',
            label: t('User'),
            sortable: false,
            render: (_value: any, row: any) => {
                const name = row?.user?.name || t('Unknown');
                const email = row?.user?.email || '';
                return (
                    <div className="flex items-center gap-2">
                        <div className="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-medium">
                            {name?.charAt(0)?.toUpperCase() || 'U'}
                        </div>
                        <div>
                            <div className="font-medium">{name}</div>
                            {email ? <div className="text-sm text-gray-500">{email}</div> : null}
                        </div>
                    </div>
                );
            },
        },
        {
            key: 'type',
            label: t('Type'),
            sortable: true,
            type: 'badge' as const,
        },
        {
            key: 'ip_address',
            label: t('IP Address'),
            sortable: true,
        },
        {
            key: 'browser_agent',
            label: t('Browser'),
            sortable: false,
            render: (value: any) => {
                const browser = String(value || '');
                const shortBrowser = browser.length > 50 ? `${browser.substring(0, 50)}...` : browser;
                return <span title={browser}>{shortBrowser || '-'}</span>;
            },
        },
        {
            key: 'created_at',
            label: t('Date & Time'),
            sortable: true,
            render: (value: any) => {
                if (!value) return <span>-</span>;
                return <span className="text-sm">{format(new Date(value), 'PPpp')}</span>;
            },
        },
    ];

    const actions = [
        {
            label: t('View Details'),
            action: 'view',
            icon: 'Eye',
        },
    ];

    return (
        <PageTemplate 
            title={t('Activity Logs')} 
            description={t('View all system activity logs')} 
            url="/admin/activity-logs"
            actions={[
                {
                    label: autoRefreshEnabled ? t('Auto-refresh: ON') : t('Auto-refresh: OFF'),
                    icon: <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />,
                    variant: autoRefreshEnabled ? 'default' : 'outline',
                    onClick: () => setAutoRefreshEnabled(!autoRefreshEnabled)
                },
                {
                    label: t('Refresh Now'),
                    icon: <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />,
                    variant: 'outline',
                    onClick: handleManualRefresh
                }
            ]}
        >
            <div className="space-y-4">
                {/* Search and Filter Bar */}
                <SearchAndFilterBar
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    onSearch={handleSearch}
                    showFilters={showFilters}
                    setShowFilters={setShowFilters}
                    hasActiveFilters={hasActiveFilters}
                    activeFilterCount={activeFilterCount}
                    onResetFilters={clearFilters}
                    filters={[
                        {
                            name: 'user_id',
                            label: t('User'),
                            type: 'select',
                            options: [{ value: 'all', label: t('All Users') }, ...(users || [])],
                            value: selectedUser,
                            onChange: (val: string) => setSelectedUser(val),
                        },
                        {
                            name: 'type',
                            label: t('Type'),
                            type: 'select',
                            options: [{ value: 'all', label: t('All Types') }, ...(types || []).map((tp: string) => ({ value: tp, label: tp }))],
                            value: selectedType,
                            onChange: (val: string) => setSelectedType(val),
                        },
                        {
                            name: 'date_from',
                            label: t('Date From'),
                            type: 'date',
                            value: dateFrom,
                            onChange: (val: Date | undefined) => setDateFrom(val),
                        },
                        {
                            name: 'date_to',
                            label: t('Date To'),
                            type: 'date',
                            value: dateTo,
                            onChange: (val: Date | undefined) => setDateTo(val),
                        },
                    ]}
                    onApplyFilters={applyFilters}
                    currentPerPage={String(pageFilters.per_page || activityLogs?.per_page || 25)}
                    onPerPageChange={(value) => {
                        const params: any = { page: 1, per_page: parseInt(value) };
                        if (searchTerm) params.search = searchTerm;
                        if (selectedUser !== 'all') params.user_id = selectedUser;
                        if (selectedType !== 'all') params.type = selectedType;
                        const dateFromParam = formatDateParam(dateFrom);
                        const dateToParam = formatDateParam(dateTo);
                        if (dateFromParam) params.date_from = dateFromParam;
                        if (dateToParam) params.date_to = dateToParam;
                        router.get(route('admin.activity-logs.index'), params, { preserveState: true, preserveScroll: true });
                    }}
                />

                {/* Table */}
                <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                    <CrudTable
                        columns={columns}
                        actions={actions}
                        data={activityLogs?.data || []}
                        from={activityLogs?.from || 1}
                        onAction={(action: string, row: any) => {
                            if (action === 'view') {
                                handleViewDetails(row);
                            }
                        }}
                        sortField={pageFilters.sort_field}
                        sortDirection={pageFilters.sort_direction}
                        onSort={handleSort}
                        permissions={auth?.permissions || []}
                    />

                    {/* Pagination */}
                    {activityLogs?.links && (
                        <Pagination
                            from={activityLogs?.from || 0}
                            to={activityLogs?.to || 0}
                            total={activityLogs?.total || 0}
                            links={activityLogs?.links}
                            entityName={t('activity logs')}
                            onPageChange={(url) => router.get(url)}
                        />
                    )}
                </div>
            </div>

            {/* Detail Modal */}
            <Dialog open={isDetailModalOpen} onOpenChange={setIsDetailModalOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('Activity Log Details')}</DialogTitle>
                    </DialogHeader>
                    {selectedLog && (
                        <div className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-gray-500">{t('ID')}</label>
                                <p className="mt-1">{selectedLog.id}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">{t('User')}</label>
                                <p className="mt-1">
                                    {selectedLog.user?.name} ({selectedLog.user?.email})
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">{t('Type')}</label>
                                <p className="mt-1">
                                    <Badge variant="outline" className="capitalize">
                                        {selectedLog.type}
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">{t('IP Address')}</label>
                                <p className="mt-1 font-mono">{selectedLog.ip_address}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">{t('Browser/User Agent')}</label>
                                <p className="mt-1 text-sm break-all">{selectedLog.browser_agent}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">{t('Date & Time')}</label>
                                <p className="mt-1">{format(new Date(selectedLog.created_at), 'PPpp')}</p>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}
