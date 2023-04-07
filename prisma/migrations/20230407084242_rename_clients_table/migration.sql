/*
  Warnings:

  - You are about to drop the `clients` table. If the table is not empty, all the data it contains will be lost.

*/
-- DropForeignKey
ALTER TABLE "public"."_community_admins" DROP CONSTRAINT "_community_admins_A_fkey";

-- DropForeignKey
ALTER TABLE "public"."_community_admins" DROP CONSTRAINT "_community_admins_B_fkey";

-- DropForeignKey
ALTER TABLE "public"."_community_members" DROP CONSTRAINT "_community_members_A_fkey";

-- DropForeignKey
ALTER TABLE "public"."_community_members" DROP CONSTRAINT "_community_members_B_fkey";

-- DropForeignKey
ALTER TABLE "public"."_community_owners" DROP CONSTRAINT "_community_owners_A_fkey";

-- DropForeignKey
ALTER TABLE "public"."_community_owners" DROP CONSTRAINT "_community_owners_B_fkey";

-- DropTable
DROP TABLE "public"."clients";

-- CreateTable
CREATE TABLE "public"."users" (
    "id" UUID NOT NULL,
    "user_id" UUID NOT NULL,
    "name" TEXT NOT NULL DEFAULT '',
    "given_name" TEXT NOT NULL DEFAULT '',
    "family_name" TEXT NOT NULL DEFAULT '',
    "email" TEXT NOT NULL DEFAULT '',
    "user_role" "public"."user_role" NOT NULL DEFAULT 'USER',
    "image_url" TEXT NOT NULL DEFAULT '',
    "phone" TEXT NOT NULL DEFAULT '',
    "created_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "updated_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT "users_pkey" PRIMARY KEY ("id")
);

-- AddForeignKey
ALTER TABLE "public"."_community_admins" ADD CONSTRAINT "_community_admins_A_fkey" FOREIGN KEY ("A") REFERENCES "public"."communities"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_admins" ADD CONSTRAINT "_community_admins_B_fkey" FOREIGN KEY ("B") REFERENCES "public"."users"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_members" ADD CONSTRAINT "_community_members_A_fkey" FOREIGN KEY ("A") REFERENCES "public"."communities"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_members" ADD CONSTRAINT "_community_members_B_fkey" FOREIGN KEY ("B") REFERENCES "public"."users"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_owners" ADD CONSTRAINT "_community_owners_A_fkey" FOREIGN KEY ("A") REFERENCES "public"."communities"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_owners" ADD CONSTRAINT "_community_owners_B_fkey" FOREIGN KEY ("B") REFERENCES "public"."users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
