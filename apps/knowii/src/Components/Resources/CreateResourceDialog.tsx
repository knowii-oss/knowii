import { useState } from 'react';
import type { CommunityResource, CommunityResourceCollection, ResourceLevel, ResourceType } from '@knowii/common';
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

export interface CreateResourceDialogSettings {
  visible: boolean;
  communityCuid: string;
  // FIXME accept either a specific collection to create the new resource into, or a list of collections to choose from
  resourceCollection?: CommunityResourceCollection;
  // FIXME make this optional
  resourceCollections: CommunityResourceCollection[];
}

interface CreateResourceDialogProps {
  settings: CreateResourceDialogSettings;
  onHide: () => void;
  onResourceCreated: (newResource: CommunityResource) => void;
}

export function CreateResourceDialog(props: CreateResourceDialogProps) {
  const { showToast } = useAppData();

  const [loading, setLoading] = useState(false);

  // All resources go in a specific collection and have a specific type
  // If a specific resource collection was passed in, we use that
  // Otherwise, we set to null and let the user choose
  const [resourceCollection, setResourceCollection] = useState<CommunityResourceCollection | null>(
    props.settings.resourceCollection ? props.settings.resourceCollection : null,
  );
  const [resourceType, setResourceType] = useState<ResourceType>('textArticle');
  const [level, setLevel] = useState<ResourceLevel>('unknown');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [url, setUrl] = useState('');

  // Requirements for step 1:
  // - Select a collection
  // - Select a type of resource
  // - Select the resource level
  // - Select a name for the resource
  // TODO once there is more than one type of resource, the user should select one explicitly
  const isStepOneValid = resourceCollection && resourceType && name && level;
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

    if (!resourceCollection || !resourceType) {
      setLoading(false);
      return;
    }

    if (resourceType === 'textArticle') {
      const response = await knowiiApiClient.resources.createTextArticle({
        communityCuid: props.settings.communityCuid,
        resourceCollectionCuid: resourceCollection?.cuid,
        url,
        name,
        description,
        level,
      });

      if ('success' === response.type && !response.errors) {
        showToast({
          severity: 'success',
          summary: 'Resource created successfully',
        });

        if (response.data) {
          const createdResource = response.data;
          props.onResourceCreated(createdResource);
        }

        // The form is reset once the dialog is closed (onAfterClose)
        props.onHide();
      } else {
        showToast({
          severity: 'error',
          summary: response.message ? response.message : 'Failed to create the resource',
          detail: response.message ? response.message : 'Please try again later.',
        });
      }
      setLoading(false);
    }
  };

  const resetForm = () => {
    setResourceCollection(props.settings.resourceCollection ?? null);
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
      open={props.settings.visible}
      onClose={props.onHide}
      title={
        <>
          <ResourceIcon />
          New resource
        </>
      }
      description="Provide the details of your new resource."
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

              {
                // Collection
                // Only shown if no specific collection was given
                !props.settings.resourceCollection && props.settings.resourceCollections && (
                  <div className="mt-4">
                    <InputLabel htmlFor="resourceCollection">Resource collection</InputLabel>
                    <Select
                      id="resourceCollection"
                      className="mt-1"
                      options={props.settings.resourceCollections.map((collection) => ({ value: collection.cuid, label: collection.name }))}
                      value={resourceCollection ? resourceCollection.cuid : null}
                      onChange={(cuid) =>
                        setResourceCollection(props.settings.resourceCollections.find((collection) => collection.cuid === cuid) ?? null)
                      }
                      placeholder="Where to store the new resource?"
                    />
                  </div>
                )
              }

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
                <Button variant="secondary" onClick={props.onHide}>
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
