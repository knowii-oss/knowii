-- DropIndex
DROP INDEX "public"."clients_name_key";

-- AlterTable
ALTER TABLE "public"."clients" ALTER COLUMN "name" SET DEFAULT '',
ALTER COLUMN "user_id" DROP DEFAULT;
