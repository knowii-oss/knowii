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
        Relationships: [
          {
            foreignKeyName: '_community_admins_A_fkey';
            columns: ['A'];
            referencedRelation: 'communities';
            referencedColumns: ['id'];
          },
          {
            foreignKeyName: '_community_admins_B_fkey';
            columns: ['B'];
            referencedRelation: 'users';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [
          {
            foreignKeyName: '_community_members_A_fkey';
            columns: ['A'];
            referencedRelation: 'communities';
            referencedColumns: ['id'];
          },
          {
            foreignKeyName: '_community_members_B_fkey';
            columns: ['B'];
            referencedRelation: 'users';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [
          {
            foreignKeyName: '_community_owners_A_fkey';
            columns: ['A'];
            referencedRelation: 'communities';
            referencedColumns: ['id'];
          },
          {
            foreignKeyName: '_community_owners_B_fkey';
            columns: ['B'];
            referencedRelation: 'users';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [];
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
        Relationships: [
          {
            foreignKeyName: '_resource_tags_A_fkey';
            columns: ['A'];
            referencedRelation: 'resources';
            referencedColumns: ['id'];
          },
          {
            foreignKeyName: '_resource_tags_B_fkey';
            columns: ['B'];
            referencedRelation: 'tags';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [];
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
        Relationships: [];
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
        Relationships: [
          {
            foreignKeyName: 'prices_product_id_fkey';
            columns: ['product_id'];
            referencedRelation: 'products';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [];
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
        Relationships: [
          {
            foreignKeyName: 'resource_collections_community_id_fkey';
            columns: ['community_id'];
            referencedRelation: 'communities';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [
          {
            foreignKeyName: 'resources_community_id_fkey';
            columns: ['community_id'];
            referencedRelation: 'communities';
            referencedColumns: ['id'];
          },
          {
            foreignKeyName: 'resources_resource_collection_id_fkey';
            columns: ['resource_collection_id'];
            referencedRelation: 'resource_collections';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [
          {
            foreignKeyName: 'subscriptions_price_id_fkey';
            columns: ['price_id'];
            referencedRelation: 'prices';
            referencedColumns: ['id'];
          },
          {
            foreignKeyName: 'subscriptions_product_id_fkey';
            columns: ['product_id'];
            referencedRelation: 'products';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [
          {
            foreignKeyName: 'tags_community_id_fkey';
            columns: ['community_id'];
            referencedRelation: 'communities';
            referencedColumns: ['id'];
          },
        ];
      };
      user_profiles: {
        Row: {
          avatar_url: string;
          bio: string;
          created_at: string;
          facebook: string;
          github: string;
          id: string;
          instagram: string;
          location: string;
          name: string;
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
          avatar_url?: string;
          bio?: string;
          created_at?: string;
          facebook?: string;
          github?: string;
          id?: string;
          instagram?: string;
          location?: string;
          name?: string;
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
          avatar_url?: string;
          bio?: string;
          created_at?: string;
          facebook?: string;
          github?: string;
          id?: string;
          instagram?: string;
          location?: string;
          name?: string;
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
        Relationships: [
          {
            foreignKeyName: 'user_profiles_user_id_external_fkey';
            columns: ['user_id_external'];
            referencedRelation: 'users';
            referencedColumns: ['user_id_external'];
          },
          {
            foreignKeyName: 'user_profiles_user_id_fkey';
            columns: ['user_id'];
            referencedRelation: 'users';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [];
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
      clean_community_name: {
        Args: {
          community_name: string;
        };
        Returns: string;
      };
      clean_username: {
        Args: {
          username: string;
        };
        Returns: string;
      };
      generate_username: {
        Args: {
          full_name: string;
        };
        Returns: string;
      };
      is_community_name_available: {
        Args: {
          name_to_check: string;
        };
        Returns: boolean;
      };
      is_username_available: {
        Args: {
          username_to_check: string;
        };
        Returns: boolean;
      };
      random_between: {
        Args: {
          low: number;
          high: number;
        };
        Returns: number;
      };
      replace_diacritics: {
        Args: {
          full_name: string;
        };
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
        Relationships: [
          {
            foreignKeyName: 'buckets_owner_fkey';
            columns: ['owner'];
            referencedRelation: 'users';
            referencedColumns: ['id'];
          },
        ];
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
        Relationships: [];
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
        Relationships: [
          {
            foreignKeyName: 'objects_bucketId_fkey';
            columns: ['bucket_id'];
            referencedRelation: 'buckets';
            referencedColumns: ['id'];
          },
          {
            foreignKeyName: 'objects_owner_fkey';
            columns: ['owner'];
            referencedRelation: 'users';
            referencedColumns: ['id'];
          },
        ];
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
        Returns: unknown;
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
