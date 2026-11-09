import { useState } from 'react';
import type { CommunityResource, ResourceLevel, ResourceType, Space } from '@knowii/common';
import {
  ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX,
  DEFAULT_TEXTAREA_ROWS,
  knowiiApiClient,
  MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION,
  MAX_LENGTH_COMMUNITY_RESOURCE_NAME,
  MIN_ACTION_TIME,
  MIN_LENGTH_COMMUNITY_RESOURCE_NAME,
  newResourceTextArticleSchema,
  resourceLevelSchemaOptions,
  resourceTypeSchemaOptions,
  sleep,
  urlSchema,
  useAppData,
} from '@knowii/common';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import InputLabel from '@/Components/InputLabel';
import { FaChevronLeft, FaChevronRight } from 'react-icons/fa';
import { Button, Dialog, Select, Spinner, Stepper, Textarea, TextInput } from '@/Components/ui';

const STEPS = ['Overview', 'Details'] as const;

const resourceTypeOptions = resourceTypeSchemaOptions.map((option) => ({ value: option.type, label: option.name }));
const resourceLevelOptions = resourceLevelSchemaOptions.map((option) => ({ value: option.level, label: option.name, icon: option.icon }));

interface CreateResourceDialogProps {
  open: boolean;
  communityCuid: string;
  // The resources space that receives the resource (ADR 0015)
  space: Space;
  onClose: () => void;
  onResourceCreated: (newResource: CommunityResource) => void;
}

export function CreateResourceDialog(props: CreateResourceDialogProps) {
  const { showToast } = useAppData();

  const [loading, setLoading] = useState(false);

  const [resourceType, setResourceType] = useState<ResourceType>('textArticle');
  const [level, setLevel] = useState<ResourceLevel>('unknown');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [url, setUrl] = useState('');

  // Requirements for step 1:
  // - Select a type of resource
  // - Select the resource level
  // - Select a name for the resource
  // TODO once there is more than one type of resource, the user should select one explicitly
  const isStepOneValid = resourceType && name && level;
  const isStepTwoValid =
    resourceType === 'textArticle' &&
    newResourceTextArticleSchema.safeParse({
      url,
      name,
      description,
      level,
    }).success;

  const [step, setStep] = useState(0);

  const goToNextStep = () => {
    setStep((current) => Math.min(current + 1, STEPS.length - 1));
  };

  const goToPreviousStep = () => {
    setStep((current) => Math.max(current - 1, 0));
  };

  const handleSubmit = async () => {
    setLoading(true);

    await sleep(MIN_ACTION_TIME);

    if (resourceType === 'textArticle') {
      const result = await knowiiApiClient.resources.create(props.communityCuid, props.space.cuid, { url, name, description, level });

      if (result.ok) {
        showToast({
          severity: 'success',
          summary: 'Resource added',
        });
        props.onResourceCreated(result.data);

        // The form is reset once the dialog is closed (onAfterClose)
        props.onClose();
      } else {
        showToast({
          severity: 'error',
          summary: result.message,
        });
      }
    }
    setLoading(false);
  };

  const resetForm = () => {
    setResourceType('textArticle');
    setLevel('unknown');
    setName('');
    setDescription('');
    setUrl('');
    setStep(0);
  };

  const urlIsValid = urlSchema.safeParse({ url }).success;

  return (
    <Dialog
      open={props.open}
      onClose={props.onClose}
      title={
        <>
          <ResourceIcon />
          New resource
        </>
      }
      description={`Add a resource to ${props.space.name}.`}
      onAfterClose={resetForm}
    >
      {loading ? (
        <div className="flex flex-row items-center justify-center py-8">
          <Spinner size="lg" label="Creating the resource" />
        </div>
      ) : (
        <>
          <Stepper steps={STEPS} currentStep={step} className="mb-2" />

          {step === 0 && (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                if (isStepOneValid) {
                  goToNextStep();
                }
              }}
              noValidate
            >
              {/* Name */}
              <div className="mt-4">
                <InputLabel htmlFor="resourceName">Name</InputLabel>
                <TextInput
                  id="resourceName"
                  wrapperClassName="mt-1"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  minLength={MIN_LENGTH_COMMUNITY_RESOURCE_NAME}
                  maxLength={MAX_LENGTH_COMMUNITY_RESOURCE_NAME}
                  allowedCharacters={ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX}
                  autoFocus
                  required
                  placeholder="Choose a name for this resource"
                />
              </div>

              {/* Type */}
              <div className="mt-4">
                <InputLabel htmlFor="resourceType">Type</InputLabel>
                <Select<ResourceType>
                  id="resourceType"
                  className="mt-1"
                  options={resourceTypeOptions}
                  value={resourceType}
                  onChange={setResourceType}
                  placeholder="Select Resource Type"
                />
              </div>

              {/* Level */}
              <div className="mt-4">
                <InputLabel htmlFor="resourceLevel">Level</InputLabel>
                <Select<ResourceLevel>
                  id="resourceLevel"
                  className="mt-1"
                  options={resourceLevelOptions}
                  value={level}
                  onChange={setLevel}
                  placeholder="Select Resource Level"
                />
              </div>

              {/* Description */}
              <div className="mt-4">
                <InputLabel htmlFor="resourceDescription">Description (optional)</InputLabel>
                <Textarea
                  id="resourceDescription"
                  wrapperClassName="mt-1"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={DEFAULT_TEXTAREA_ROWS}
                  maxLength={MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION}
                  placeholder="Why is it useful/important? What is it about?"
                />
              </div>

              <div className="flex justify-end gap-2 border-t border-border pt-4 mt-6">
                <Button variant="secondary" onClick={props.onClose}>
                  Cancel
                </Button>
                <Button type="submit" disabled={!isStepOneValid} rightIcon={<FaChevronRight aria-hidden="true" />}>
                  Next
                </Button>
              </div>
            </form>
          )}

          {step === 1 && (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                if (isStepOneValid && isStepTwoValid) {
                  void handleSubmit();
                }
              }}
              noValidate
            >
              {resourceType === 'textArticle' && (
                <div className="mt-4">
                  {/* URL */}
                  <InputLabel htmlFor="resourceUrl">Link</InputLabel>
                  <TextInput
                    id="resourceUrl"
                    type="url"
                    wrapperClassName="mt-1"
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    invalid={url.length > 0 && !urlIsValid}
                    autoFocus
                    required
                    placeholder="https://..."
                  />
                </div>
              )}

              <div className="flex justify-end gap-2 border-t border-border pt-4 mt-6">
                <Button variant="secondary" onClick={goToPreviousStep} leftIcon={<FaChevronLeft aria-hidden="true" />}>
                  Back
                </Button>
                <Button type="submit" disabled={!isStepOneValid || !isStepTwoValid}>
                  Create 🚀
                </Button>
              </div>
            </form>
          )}
        </>
      )}
    </Dialog>
  );
}
