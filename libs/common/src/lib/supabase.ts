export type Json = string | number | boolean | null | { [key: string]: Json } | Json[];

export interface Database {
  graphql_public: {
    Tables: {
      [_ in never]: never;
    };
    Views: {
      [_ in never]: never;
    };
    Functions: {
      graphql: {
        Args: {
          operationName?: string;
          query?: string;
          variables?: Json;
          extensions?: Json;
        };
        Returns: Json;
      };
    };
    Enums: {
      [_ in never]: never;
    };
    CompositeTypes: {
      [_ in never]: never;
    };
  };
  public: {
    Tables: {
      _community_admins: {
        Row: {
          A: string;
          B: string;
        };
        Insert: {
          A: string;
          B: string;
        };
        Update: {
          A?: string;
          B?: string;
        };
      };
      _community_members: {
        Row: {
          A: string;
          B: string;
        };
        Insert: {
          A: string;
          B: string;
        };
        Update: {
          A?: string;
          B?: string;
        };
      };
      _community_owners: {
        Row: {
          A: string;
          B: string;
        };
        Insert: {
          A: string;
          B: string;
        };
        Update: {
          A?: string;
          B?: string;
        };
      };
      _prisma_migrations: {
        Row: {
          applied_steps_count: number;
          checksum: string;
          finished_at: string | null;
          id: string;
          logs: string | null;
          migration_name: string;
          rolled_back_at: string | null;
          started_at: string;
        };
        Insert: {
          applied_steps_count?: number;
          checksum: string;
          finished_at?: string | null;
          id: string;
          logs?: string | null;
          migration_name: string;
          rolled_back_at?: string | null;
          started_at?: string;
        };
        Update: {
          applied_steps_count?: number;
          checksum?: string;
          finished_at?: string | null;
          id?: string;
          logs?: string | null;
          migration_name?: string;
          rolled_back_at?: string | null;
          started_at?: string;
        };
      };
      _resource_tags: {
        Row: {
          A: string;
          B: string;
        };
        Insert: {
          A: string;
          B: string;
        };
        Update: {
          A?: string;
          B?: string;
        };
      };
      communities: {
        Row: {
          created_at: string;
          description: string;
          id: string;
          name: string;
          slug: string;
          updated_at: string;
          visibility: Database['public']['Enums']['community_visibility'];
        };
        Insert: {
          created_at?: string;
          description?: string;
          id?: string;
          name: string;
          slug: string;
          updated_at?: string;
          visibility?: Database['public']['Enums']['community_visibility'];
        };
        Update: {
          created_at?: string;
          description?: string;
          id?: string;
          name?: string;
          slug?: string;
          updated_at?: string;
          visibility?: Database['public']['Enums']['community_visibility'];
        };
      };
      customers: {
        Row: {
          stripe_customer_id: string;
          user_id: string;
        };
        Insert: {
          stripe_customer_id: string;
          user_id: string;
        };
        Update: {
          stripe_customer_id?: string;
          user_id?: string;
        };
      };
      prices: {
        Row: {
          active: boolean | null;
          currency: string | null;
          description: string | null;
          id: string;
          interval: Database['public']['Enums']['pricing_plan_interval'] | null;
          interval_count: number | null;
          metadata: Json | null;
          product_id: string | null;
          trial_period_days: number | null;
          type: Database['public']['Enums']['pricing_type'] | null;
          unit_amount: number | null;
        };
        Insert: {
          active?: boolean | null;
          currency?: string | null;
          description?: string | null;
          id?: string;
          interval?: Database['public']['Enums']['pricing_plan_interval'] | null;
          interval_count?: number | null;
          metadata?: Json | null;
          product_id?: string | null;
          trial_period_days?: number | null;
          type?: Database['public']['Enums']['pricing_type'] | null;
          unit_amount?: number | null;
        };
        Update: {
          active?: boolean | null;
          currency?: string | null;
          description?: string | null;
          id?: string;
          interval?: Database['public']['Enums']['pricing_plan_interval'] | null;
          interval_count?: number | null;
          metadata?: Json | null;
          product_id?: string | null;
          trial_period_days?: number | null;
          type?: Database['public']['Enums']['pricing_type'] | null;
          unit_amount?: number | null;
        };
      };
      products: {
        Row: {
          active: boolean | null;
          description: string | null;
          id: string;
          image: string | null;
          metadata: Json | null;
          name: string | null;
        };
        Insert: {
          active?: boolean | null;
          description?: string | null;
          id?: string;
          image?: string | null;
          metadata?: Json | null;
          name?: string | null;
        };
        Update: {
          active?: boolean | null;
          description?: string | null;
          id?: string;
          image?: string | null;
          metadata?: Json | null;
          name?: string | null;
        };
      };
      resource_collections: {
        Row: {
          community_id: string;
          created_at: string;
          description: string;
          id: string;
          name: string;
          slug: string;
          updated_at: string;
        };
        Insert: {
          community_id: string;
          created_at?: string;
          description?: string;
          id?: string;
          name?: string;
          slug: string;
          updated_at?: string;
        };
        Update: {
          community_id?: string;
          created_at?: string;
          description?: string;
          id?: string;
          name?: string;
          slug?: string;
          updated_at?: string;
        };
      };
      resources: {
        Row: {
          community_id: string;
          content: string;
          created_at: string;
          description: string;
          down_votes: number;
          id: string;
          name: string;
          resource_collection_id: string;
          slug: string;
          source: string;
          up_votes: number;
          updated_at: string;
        };
        Insert: {
          community_id: string;
          content?: string;
          created_at?: string;
          description?: string;
          down_votes?: number;
          id?: string;
          name?: string;
          resource_collection_id: string;
          slug: string;
          source?: string;
          up_votes?: number;
          updated_at?: string;
        };
        Update: {
          community_id?: string;
          content?: string;
          created_at?: string;
          description?: string;
          down_votes?: number;
          id?: string;
          name?: string;
          resource_collection_id?: string;
          slug?: string;
          source?: string;
          up_votes?: number;
          updated_at?: string;
        };
      };
      subscriptions: {
        Row: {
          cancel_at: string | null;
          cancel_at_period_end: boolean | null;
          canceled_at: string | null;
          created_at: string;
          current_period_end: string;
          current_period_start: string;
          ended_at: string | null;
          id: string;
          metadata: Json | null;
          price_id: string | null;
          product_id: string | null;
          quantity: number | null;
          status: Database['public']['Enums']['subscription_status'] | null;
          trial_end: string | null;
          trial_start: string | null;
          user_id: string;
          user_id_external: string | null;
        };
        Insert: {
          cancel_at?: string | null;
          cancel_at_period_end?: boolean | null;
          canceled_at?: string | null;
          created_at?: string;
          current_period_end?: string;
          current_period_start?: string;
          ended_at?: string | null;
          id?: string;
          metadata?: Json | null;
          price_id?: string | null;
          product_id?: string | null;
          quantity?: number | null;
          status?: Database['public']['Enums']['subscription_status'] | null;
          trial_end?: string | null;
          trial_start?: string | null;
          user_id: string;
          user_id_external?: string | null;
        };
        Update: {
          cancel_at?: string | null;
          cancel_at_period_end?: boolean | null;
          canceled_at?: string | null;
          created_at?: string;
          current_period_end?: string;
          current_period_start?: string;
          ended_at?: string | null;
          id?: string;
          metadata?: Json | null;
          price_id?: string | null;
          product_id?: string | null;
          quantity?: number | null;
          status?: Database['public']['Enums']['subscription_status'] | null;
          trial_end?: string | null;
          trial_start?: string | null;
          user_id?: string;
          user_id_external?: string | null;
        };
      };
      tags: {
        Row: {
          community_id: string;
          created_at: string;
          id: string;
          name: string;
          updated_at: string;
        };
        Insert: {
          community_id: string;
          created_at?: string;
          id?: string;
          name: string;
          updated_at?: string;
        };
        Update: {
          community_id?: string;
          created_at?: string;
          id?: string;
          name?: string;
          updated_at?: string;
        };
      };
      user_profiles: {
        Row: {
          bio: string;
          created_at: string;
          facebook: string;
          family_name: string;
          github: string;
          given_name: string;
          id: string;
          image_url: string;
          instagram: string;
          location: string;
          organization_link: string;
          organization_name: string;
          phone: string;
          tiktok: string;
          twitter: string;
          updated_at: string;
          user_id: string;
          user_id_external: string | null;
          website: string;
        };
        Insert: {
          bio?: string;
          created_at?: string;
          facebook?: string;
          family_name?: string;
          github?: string;
          given_name?: string;
          id?: string;
          image_url?: string;
          instagram?: string;
          location?: string;
          organization_link?: string;
          organization_name?: string;
          phone?: string;
          tiktok?: string;
          twitter?: string;
          updated_at?: string;
          user_id: string;
          user_id_external?: string | null;
          website?: string;
        };
        Update: {
          bio?: string;
          created_at?: string;
          facebook?: string;
          family_name?: string;
          github?: string;
          given_name?: string;
          id?: string;
          image_url?: string;
          instagram?: string;
          location?: string;
          organization_link?: string;
          organization_name?: string;
          phone?: string;
          tiktok?: string;
          twitter?: string;
          updated_at?: string;
          user_id?: string;
          user_id_external?: string | null;
          website?: string;
        };
      };
      users: {
        Row: {
          created_at: string;
          email: string;
          id: string;
          updated_at: string;
          user_id_external: string | null;
          user_role: Database['public']['Enums']['user_role'];
          username: string;
        };
        Insert: {
          created_at?: string;
          email: string;
          id?: string;
          updated_at?: string;
          user_id_external?: string | null;
          user_role?: Database['public']['Enums']['user_role'];
          username: string;
        };
        Update: {
          created_at?: string;
          email?: string;
          id?: string;
          updated_at?: string;
          user_id_external?: string | null;
          user_role?: Database['public']['Enums']['user_role'];
          username?: string;
        };
      };
    };
    Views: {
      [_ in never]: never;
    };
    Functions: {
      auth_email: {
        Args: Record<PropertyKey, never>;
        Returns: string;
      };
      auth_jwt: {
        Args: Record<PropertyKey, never>;
        Returns: string;
      };
      auth_role: {
        Args: Record<PropertyKey, never>;
        Returns: string;
      };
      auth_uid: {
        Args: Record<PropertyKey, never>;
        Returns: string;
      };
    };
    Enums: {
      community_visibility: 'PUBLIC' | 'PRIVATE';
      pricing_plan_interval: 'day' | 'week' | 'month' | 'year';
      pricing_type: 'one_time' | 'recurring';
      subscription_status: 'trialing' | 'active' | 'canceled' | 'incomplete' | 'incomplete_expired' | 'past_due' | 'paused' | 'unpaid';
      user_role: 'USER' | 'ADMIN';
    };
    CompositeTypes: {
      [_ in never]: never;
    };
  };
  storage: {
    Tables: {
      buckets: {
        Row: {
          allowed_mime_types: string[] | null;
          avif_autodetection: boolean | null;
          created_at: string | null;
          file_size_limit: number | null;
          id: string;
          name: string;
          owner: string | null;
          public: boolean | null;
          updated_at: string | null;
        };
        Insert: {
          allowed_mime_types?: string[] | null;
          avif_autodetection?: boolean | null;
          created_at?: string | null;
          file_size_limit?: number | null;
          id: string;
          name: string;
          owner?: string | null;
          public?: boolean | null;
          updated_at?: string | null;
        };
        Update: {
          allowed_mime_types?: string[] | null;
          avif_autodetection?: boolean | null;
          created_at?: string | null;
          file_size_limit?: number | null;
          id?: string;
          name?: string;
          owner?: string | null;
          public?: boolean | null;
          updated_at?: string | null;
        };
      };
      migrations: {
        Row: {
          executed_at: string | null;
          hash: string;
          id: number;
          name: string;
        };
        Insert: {
          executed_at?: string | null;
          hash: string;
          id: number;
          name: string;
        };
        Update: {
          executed_at?: string | null;
          hash?: string;
          id?: number;
          name?: string;
        };
      };
      objects: {
        Row: {
          bucket_id: string | null;
          created_at: string | null;
          id: string;
          last_accessed_at: string | null;
          metadata: Json | null;
          name: string | null;
          owner: string | null;
          path_tokens: string[] | null;
          updated_at: string | null;
          version: string | null;
        };
        Insert: {
          bucket_id?: string | null;
          created_at?: string | null;
          id?: string;
          last_accessed_at?: string | null;
          metadata?: Json | null;
          name?: string | null;
          owner?: string | null;
          path_tokens?: string[] | null;
          updated_at?: string | null;
          version?: string | null;
        };
        Update: {
          bucket_id?: string | null;
          created_at?: string | null;
          id?: string;
          last_accessed_at?: string | null;
          metadata?: Json | null;
          name?: string | null;
          owner?: string | null;
          path_tokens?: string[] | null;
          updated_at?: string | null;
          version?: string | null;
        };
      };
    };
    Views: {
      [_ in never]: never;
    };
    Functions: {
      can_insert_object: {
        Args: {
          bucketid: string;
          name: string;
          owner: string;
          metadata: Json;
        };
        Returns: undefined;
      };
      extension: {
        Args: {
          name: string;
        };
        Returns: string;
      };
      filename: {
        Args: {
          name: string;
        };
        Returns: string;
      };
      foldername: {
        Args: {
          name: string;
        };
        Returns: string[];
      };
      get_size_by_bucket: {
        Args: Record<PropertyKey, never>;
        Returns: {
          size: number;
          bucket_id: string;
        }[];
      };
      search: {
        Args: {
          prefix: string;
          bucketname: string;
          limits?: number;
          levels?: number;
          offsets?: number;
          search?: string;
          sortcolumn?: string;
          sortorder?: string;
        };
        Returns: {
          name: string;
          id: string;
          updated_at: string;
          created_at: string;
          last_accessed_at: string;
          metadata: Json;
        }[];
      };
    };
    Enums: {
      [_ in never]: never;
    };
    CompositeTypes: {
      [_ in never]: never;
    };
  };
}
