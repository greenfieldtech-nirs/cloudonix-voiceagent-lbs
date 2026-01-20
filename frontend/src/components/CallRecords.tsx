import React, { useState, useEffect } from 'react';
import { Table, Button, Input, Select, DatePicker, Space, Tag, Card, Form, Row, Col, Pagination, Modal, Descriptions } from 'antd';
import { SearchOutlined, FilterOutlined, DownloadOutlined, EyeOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';

const { RangePicker } = DatePicker;
const { Option } = Select;

interface CallRecord {
  id: number;
  session_token: string;
  direction: string;
  direction_display: string;
  from_number: string;
  to_number: string;
  status: string;
  status_display: string;
  start_time: string;
  end_time: string;
  duration: number;
  duration_formatted: string;
  agent?: {
    id: number;
    name: string;
  };
  group?: {
    id: number;
    name: string;
  };
  is_completed: boolean;
  is_successful: boolean;
}

interface FilterOptions {
  statuses: string[];
  directions: string[];
  agents: Array<{ id: number; name: string }>;
  groups: Array<{ id: number; name: string }>;
  date_range: {
    min: string;
    max: string;
  };
}

const CallRecords: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<CallRecord[]>([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 25,
    total: 0,
  });
  const [filters, setFilters] = useState<Record<string, any>>({});
  const [filterOptions, setFilterOptions] = useState<FilterOptions | null>(null);
  const [selectedRecord, setSelectedRecord] = useState<CallRecord | null>(null);
  const [detailModalVisible, setDetailModalVisible] = useState(false);

  // Load filter options
  useEffect(() => {
    fetchFilterOptions();
  }, []);

  // Load data when filters or pagination changes
  useEffect(() => {
    fetchCallRecords();
  }, [pagination.current, pagination.pageSize, filters]);

  const fetchFilterOptions = async () => {
    try {
      const response = await fetch('/api/call-records/filters/options');
      const result = await response.json();
      setFilterOptions(result.data);
    } catch (error) {
      console.error('Failed to load filter options');
    }
  };

  const fetchCallRecords = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: pagination.current.toString(),
        per_page: pagination.pageSize.toString(),
        ...filters,
      });

      const response = await fetch(`/api/call-records?${params}`);
      const result = await response.json();

      setData(result.data.data);
      setPagination((prev: any) => ({
        ...prev,
        total: result.data.total,
      }));
    } catch (error) {
      console.error('Failed to load call records');
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key: string, value: any) => {
    setFilters((prev: Record<string, any>) => ({
      ...prev,
      [key]: value,
    }));
    setPagination((prev: any) => ({ ...prev, current: 1 })); // Reset to first page
  };

  const handleDateRangeChange = (dates: any) => {
    if (dates) {
      setFilters((prev: Record<string, any>) => ({
        ...prev,
        start_date: dates[0].format('YYYY-MM-DD'),
        end_date: dates[1].format('YYYY-MM-DD'),
      }));
    } else {
      setFilters((prev: Record<string, any>) => {
        const { start_date, end_date, ...rest } = prev;
        return rest;
      });
    }
    setPagination((prev: any) => ({ ...prev, current: 1 }));
  };

  const handleExport = async (format: 'csv' | 'json') => {
    try {
      const response = await fetch('/api/call-records/export', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          format,
          filters,
        }),
      });
      const result = await response.json();

      if (response.ok) {
        console.log('Export queued:', result.data.export_id);
        // In a real app, you'd show a notification and maybe poll for status
      }
    } catch (error) {
      console.error('Export failed');
    }
  };

  const showRecordDetails = (record: CallRecord) => {
    setSelectedRecord(record);
    setDetailModalVisible(true);
  };

  const columns = [
    {
      title: 'Time',
      dataIndex: 'start_time',
      key: 'start_time',
      render: (time: string) => dayjs(time).format('MMM DD, HH:mm:ss'),
      sorter: true,
    },
    {
      title: 'Direction',
      dataIndex: 'direction_display',
      key: 'direction',
      render: (direction: string) => (
        <Tag color={direction === 'Inbound' ? 'blue' : 'green'}>
          {direction}
        </Tag>
      ),
    },
    {
      title: 'From',
      dataIndex: 'from_number',
      key: 'from_number',
    },
    {
      title: 'To',
      dataIndex: 'to_number',
      key: 'to_number',
    },
    {
      title: 'Status',
      dataIndex: 'status_display',
      key: 'status',
      render: (status: string, record: CallRecord) => (
        <Tag color={
          record.is_successful ? 'success' :
          record.status === 'ringing' ? 'processing' :
          record.status === 'completed' ? 'default' : 'error'
        }>
          {status}
        </Tag>
      ),
    },
    {
      title: 'Duration',
      dataIndex: 'duration_formatted',
      key: 'duration',
    },
    {
      title: 'Agent',
      dataIndex: ['agent', 'name'],
      key: 'agent',
      render: (name: string) => name || 'N/A',
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: any, record: CallRecord) => (
        <Button
          icon={<EyeOutlined />}
          size="small"
          onClick={() => showRecordDetails(record)}
        >
          Details
        </Button>
      ),
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <div style={{ marginBottom: 24, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>Call Records</h1>
        <Space>
          <Button icon={<DownloadOutlined />} onClick={() => handleExport('csv')}>
            Export CSV
          </Button>
          <Button icon={<DownloadOutlined />} onClick={() => handleExport('json')}>
            Export JSON
          </Button>
        </Space>
      </div>

      <Card style={{ marginBottom: 24 }}>
        <Form layout="inline">
          <Row gutter={16} style={{ width: '100%' }}>
            <Col span={6}>
              <Form.Item label="Date Range">
                <RangePicker
                  value={filters.start_date && filters.end_date ? [
                    dayjs(filters.start_date),
                    dayjs(filters.end_date)
                  ] : null}
                  onChange={handleDateRangeChange}
                  style={{ width: '100%' }}
                />
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item label="Direction">
                <Select
                  value={filters.direction}
                  onChange={(value) => handleFilterChange('direction', value)}
                  allowClear
                  style={{ width: '100%' }}
                >
                  {filterOptions?.directions.map(dir => (
                    <Option key={dir} value={dir}>{dir.charAt(0).toUpperCase() + dir.slice(1)}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item label="Status">
                <Select
                  value={filters.status}
                  onChange={(value) => handleFilterChange('status', value)}
                  allowClear
                  style={{ width: '100%' }}
                >
                  {filterOptions?.statuses.map(status => (
                    <Option key={status} value={status}>
                      {status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item label="Agent">
                <Select
                  value={filters.agent_id}
                  onChange={(value) => handleFilterChange('agent_id', value)}
                  allowClear
                  showSearch
                  optionFilterProp="children"
                  style={{ width: '100%' }}
                >
                  {filterOptions?.agents.map(agent => (
                    <Option key={agent.id} value={agent.id}>{agent.name}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item label="Search">
                <Input
                  placeholder="Phone number or session"
                  value={filters.from_number || filters.to_number || filters.session_token}
                   onChange={(e) => {
                     const value = e.target.value;
                     setFilters((prev: Record<string, any>) => ({
                       ...prev,
                       from_number: value,
                       to_number: value,
                       session_token: value,
                     }));
                   }}
                  prefix={<SearchOutlined />}
                />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Card>

      <Table
        columns={columns}
        dataSource={data}
        loading={loading}
        pagination={false}
        rowKey="id"
        size="middle"
      />

      <div style={{ marginTop: 24, textAlign: 'right' }}>
        <Pagination
          current={pagination.current}
          pageSize={pagination.pageSize}
          total={pagination.total}
          onChange={(page, pageSize) => {
            setPagination(prev => ({
              ...prev,
              current: page,
              pageSize: pageSize || prev.pageSize,
            }));
          }}
          showSizeChanger
          showQuickJumper
          showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} records`}
        />
      </div>

      <Modal
        title="Call Record Details"
        open={detailModalVisible}
        onCancel={() => setDetailModalVisible(false)}
        footer={null}
        width={800}
      >
        {selectedRecord && (
          <Descriptions bordered column={2}>
            <Descriptions.Item label="Session ID">{selectedRecord.session_token}</Descriptions.Item>
            <Descriptions.Item label="Call ID">{selectedRecord.id}</Descriptions.Item>
            <Descriptions.Item label="Direction">{selectedRecord.direction_display}</Descriptions.Item>
            <Descriptions.Item label="Status">{selectedRecord.status_display}</Descriptions.Item>
            <Descriptions.Item label="From">{selectedRecord.from_number}</Descriptions.Item>
            <Descriptions.Item label="To">{selectedRecord.to_number}</Descriptions.Item>
            <Descriptions.Item label="Start Time">{dayjs(selectedRecord.start_time).format('MMM DD, YYYY HH:mm:ss')}</Descriptions.Item>
            <Descriptions.Item label="End Time">
              {selectedRecord.end_time ? dayjs(selectedRecord.end_time).format('MMM DD, YYYY HH:mm:ss') : 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Duration">{selectedRecord.duration_formatted}</Descriptions.Item>
            <Descriptions.Item label="Agent">{selectedRecord.agent?.name || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Group">{selectedRecord.group?.name || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Completed">{selectedRecord.is_completed ? 'Yes' : 'No'}</Descriptions.Item>
            <Descriptions.Item label="Successful">{selectedRecord.is_successful ? 'Yes' : 'No'}</Descriptions.Item>
          </Descriptions>
        )}
      </Modal>
    </div>
  );
};

export default CallRecords;