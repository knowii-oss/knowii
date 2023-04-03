-- CreateTable
CREATE TABLE "public"."resource_collections" (
    "id" UUID NOT NULL,
    "name" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "description" TEXT NOT NULL DEFAULT '',
    "community_id" UUID NOT NULL,
    "created_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "updated_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT "resource_collections_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "resource_collections_slug_key" ON "public"."resource_collections"("slug");

-- CreateIndex
CREATE INDEX "idx_resource_collection_name" ON "public"."resource_collections"("name");

-- AddForeignKey
ALTER TABLE "public"."resource_collections" ADD CONSTRAINT "resource_collections_community_id_fkey" FOREIGN KEY ("community_id") REFERENCES "public"."communities"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
