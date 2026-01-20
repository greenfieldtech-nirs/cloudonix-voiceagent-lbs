import { useEffect, useRef, useState } from 'react';

// WebSocket connection states
export enum ConnectionState {
  CONNECTING = 'connecting',
  CONNECTED = 'connected',
  DISCONNECTED = 'disconnected',
  RECONNECTING = 'reconnecting',
  ERROR = 'error',
}

// WebSocket hook for real-time updates
export function useWebSocket(url: string, options: {
  protocols?: string | string[];
  reconnectAttempts?: number;
  reconnectInterval?: number;
  onMessage?: (event: MessageEvent) => void;
  onOpen?: (event: Event) => void;
  onClose?: (event: CloseEvent) => void;
  onError?: (event: Event) => void;
} = {}) {
  const [connectionState, setConnectionState] = useState<ConnectionState>(ConnectionState.DISCONNECTED);
  const [lastMessage, setLastMessage] = useState<MessageEvent | null>(null);
  const [error, setError] = useState<Event | null>(null);

  const wsRef = useRef<WebSocket | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const reconnectAttemptsRef = useRef(0);

  const {
    protocols,
    reconnectAttempts = 5,
    reconnectInterval = 3000,
    onMessage,
    onOpen,
    onClose,
    onError,
  } = options;

  const connect = () => {
    try {
      setConnectionState(ConnectionState.CONNECTING);
      const ws = new WebSocket(url, protocols);
      wsRef.current = ws;

      ws.onopen = (event) => {
        setConnectionState(ConnectionState.CONNECTED);
        setError(null);
        reconnectAttemptsRef.current = 0;
        onOpen?.(event);
      };

      ws.onmessage = (event) => {
        setLastMessage(event);
        onMessage?.(event);
      };

      ws.onclose = (event) => {
        setConnectionState(ConnectionState.DISCONNECTED);
        wsRef.current = null;
        onClose?.(event);

        // Attempt to reconnect if not a clean close
        if (!event.wasClean && reconnectAttemptsRef.current < reconnectAttempts) {
          setConnectionState(ConnectionState.RECONNECTING);
          reconnectAttemptsRef.current += 1;

          reconnectTimeoutRef.current = setTimeout(() => {
            connect();
          }, reconnectInterval);
        }
      };

      ws.onerror = (event) => {
        setError(event);
        setConnectionState(ConnectionState.ERROR);
        onError?.(event);
      };

    } catch (err) {
      setError(err as Event);
      setConnectionState(ConnectionState.ERROR);
    }
  };

  const disconnect = () => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
      reconnectTimeoutRef.current = null;
    }

    if (wsRef.current) {
      wsRef.current.close(1000, 'Client disconnect');
      wsRef.current = null;
    }

    setConnectionState(ConnectionState.DISCONNECTED);
  };

  const send = (data: string | ArrayBuffer | Blob | ArrayBufferView) => {
    if (wsRef.current && wsRef.current.readyState === WebSocket.OPEN) {
      wsRef.current.send(data);
      return true;
    }
    return false;
  };

  useEffect(() => {
    connect();

    return () => {
      disconnect();
    };
  }, [url]);

  return {
    connectionState,
    lastMessage,
    error,
    send,
    disconnect,
    reconnect: connect,
  };
}

// Hook for Laravel Echo WebSocket integration
export function useEcho(channelName: string, options: {
  auth?: {
    headers?: Record<string, string>;
  };
  csrfToken?: string;
} = {}) {
  const [channel, setChannel] = useState<any>(null);
  const [events, setEvents] = useState<any[]>([]);

  useEffect(() => {
    // In a real implementation, this would use Laravel Echo
    // For now, we'll simulate the functionality

    const mockChannel = {
      listen: (event: string, callback: Function) => {
        // Store event listeners
        setEvents(prev => [...prev, { event, callback }]);
        return mockChannel;
      },
      stopListening: (event: string) => {
        setEvents(prev => prev.filter(e => e.event !== event));
        return mockChannel;
      },
      unsubscribe: () => {
        setEvents([]);
        return mockChannel;
      },
    };

    setChannel(mockChannel);

    return () => {
      mockChannel.unsubscribe();
    };
  }, [channelName]);

  const broadcast = (event: string, data: any) => {
    // In a real implementation, this would broadcast to the channel
    console.log('Broadcasting event:', event, data);
  };

  return {
    channel,
    broadcast,
  };
}

// Hook for real-time analytics updates
export function useRealtimeAnalytics(tenantId: string) {
  const [analytics, setAnalytics] = useState<any>(null);
  const [isConnected, setIsConnected] = useState(false);

  const { channel } = useEcho(`tenant.${tenantId}.analytics`);

  useEffect(() => {
    if (channel) {
      channel.listen('analytics.updated', (event: any) => {
        setAnalytics(event);
      });

      setIsConnected(true);

      return () => {
        channel.stopListening('analytics.updated');
      };
    }
  }, [channel]);

  return {
    analytics,
    isConnected,
  };
}

// Hook for real-time call updates
export function useRealtimeCalls(tenantId: string) {
  const [calls, setCalls] = useState<any[]>([]);
  const [isConnected, setIsConnected] = useState(false);

  const { channel } = useEcho(`tenant.${tenantId}.calls`);

  useEffect(() => {
    if (channel) {
      channel.listen('call.created', (event: any) => {
        setCalls(prev => [event.call_record, ...prev.slice(0, 9)]); // Keep last 10
      });

      channel.listen('call.updated', (event: any) => {
        setCalls(prev => prev.map(call =>
          call.id === event.call_record.id ? event.call_record : call
        ));
      });

      setIsConnected(true);

      return () => {
        channel.stopListening('call.created');
        channel.stopListening('call.updated');
      };
    }
  }, [channel]);

  return {
    calls,
    isConnected,
  };
}