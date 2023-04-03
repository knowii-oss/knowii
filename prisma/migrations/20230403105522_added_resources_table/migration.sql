-- CreateTable
CREATE TABLE "public"."resources" (
    "id" UUID NOT NULL,
    "name" TEXT NOT NULL,
    "description" TEXT NOT NULL DEFAULT '',
    "link" TEXT NOT NULL DEFAULT '',
    "slug" TEXT NOT NULL,
    "community_id" UUID NOT NULL,
    "resource_collection_id" UUID NOT NULL,
    "created_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "updated_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT "resources_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "idx_resource_slug" ON "public"."resources"("slug");

-- AddForeignKey
ALTER TABLE "public"."resources" ADD CONSTRAINT "resources_resource_collection_id_fkey" FOREIGN KEY ("resource_collection_id") REFERENCES "public"."resource_collections"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."resources" ADD CONSTRAINT "resources_community_id_fkey" FOREIGN KEY ("community_id") REFERENCES "public"."communities"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
